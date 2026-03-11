<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utils\ItemParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches SkyBlock profile data directly from the Hypixel API (v2)
 * and transforms it into the format expected by the frontend.
 *
 * Logic mirrors how SkyCrypt (github.com/SkyCryptWebsite/SkyCrypt) works:
 *   1. Resolve username → UUID via Mojang API
 *   2. Call https://api.hypixel.net/v2/skyblock/profiles?key=…&uuid=…
 *   3. Parse skills, slayers, dungeons from raw member data
 *   4. Cache the transformed result for 5 minutes
 */
class SkyCryptProxyController extends Controller
{
    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    /** Max retries on rate-limit / server error. */
    private const MAX_RETRIES = 3;

    // ─── Skill XP tables (from SkyCrypt constants) ───────────────────
    private const SKILL_XP_TABLE = [
        0, 50, 175, 375, 675, 1175, 1925, 2925, 4425, 6425,
        9925, 14925, 22425, 32425, 47425, 67425, 97425, 147425, 222425, 322425,
        522425, 822425, 1222425, 1722425, 2322425, 3022425, 3822425, 4722425, 5722425, 6822425,
        8022425, 9322425, 10722425, 12222425, 13822425, 15522425, 17322425, 19222425, 21222425, 23322425,
        25522425, 27822425, 30222425, 32722425, 35322425, 38072425, 40972425, 44072425, 47472425, 51172425,
        55172425, 59472425, 64072425, 68972425, 74172425, 79672425, 85472425, 91572425, 97972425, 104672425,
        111672425,
    ];

    private const DUNGEON_XP_TABLE = [
        0, 50, 125, 235, 395, 625, 955, 1425, 2095, 3045,
        4385, 6275, 8940, 12700, 17960, 25340, 35640, 50040, 70040, 97640,
        135640, 188140, 259640, 356640, 488640, 668640, 911640, 1239640, 1684640, 2284640,
        3084640, 4149640, 5559640, 7459640, 9959640, 13259640, 17559640, 23159640, 30359640, 39559640,
        51559640, 66559640, 85559640, 109559640, 139559640, 177559640, 225559640, 285559640, 360559640, 453559640,
    ];

    private const SLAYER_XP_TABLE = [
        'zombie'    => [0, 5, 15, 200, 1000, 5000, 20000, 100000, 400000, 1000000],
        'spider'    => [0, 5, 25, 200, 1000, 5000, 20000, 100000, 400000, 1000000],
        'wolf'      => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'enderman'  => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'blaze'     => [0, 10, 30, 250, 1500, 5000, 20000, 100000, 400000, 1000000],
        'vampire'   => [0, 20, 75, 240, 840, 2400],
    ];

    /** Cost in coins per boss tier (from SkyCrypt). */
    private const SLAYER_COST = [
        1 => 2000,
        2 => 7500,
        3 => 20000,
        4 => 50000,
        5 => 100000,
    ];

    /** Display names for each slayer type (from SkyCrypt). */
    private const SLAYER_INFO = [
        'zombie'   => 'Revenant Horror',
        'spider'   => 'Tarantula Broodfather',
        'wolf'     => 'Sven Packmaster',
        'enderman' => 'Voidgloom Seraph',
        'blaze'    => 'Inferno Demonlord',
        'vampire'  => 'Riftstalker Bloodfiend',
    ];

    /** Minecraft color code name → hex color (for rank colors). */
    private const MC_COLOR_MAP = [
        'BLACK'        => '#000000',
        'DARK_BLUE'    => '#0000AA',
        'DARK_GREEN'   => '#00AA00',
        'DARK_AQUA'    => '#00AAAA',
        'DARK_RED'     => '#AA0000',
        'DARK_PURPLE'  => '#AA00AA',
        'GOLD'         => '#FFAA00',
        'GRAY'         => '#AAAAAA',
        'DARK_GRAY'    => '#555555',
        'BLUE'         => '#5555FF',
        'GREEN'        => '#55FF55',
        'AQUA'         => '#55FFFF',
        'RED'          => '#FF5555',
        'LIGHT_PURPLE' => '#FF55FF',
        'YELLOW'       => '#FFFF55',
        'WHITE'        => '#FFFFFF',
    ];

    /** Pet XP required per level (from SkyCrypt constants). */
    private const PET_LEVELS = [
        100, 110, 120, 130, 145, 160, 175, 190, 210, 230,
        250, 275, 300, 330, 360, 400, 440, 490, 540, 600,
        660, 730, 800, 880, 960, 1050, 1150, 1260, 1380, 1510,
        1650, 1800, 1960, 2130, 2310, 2500, 2700, 2920, 3160, 3420,
        3700, 4000, 4350, 4750, 5200, 5700, 6300, 7000, 7800, 8700,
        9700, 10800, 12000, 13300, 14700, 16200, 17800, 19500, 21300, 23200,
        25200, 27400, 29800, 32400, 35200, 38200, 41400, 44800, 48400, 52200,
        56200, 60400, 64800, 69400, 74200, 79200, 84500, 90000, 95800, 101800,
        108100, 114700, 121600, 128800, 136300, 144100, 152200, 160600, 169300, 178300,
        187600, 197200, 207100, 217300, 227800, 238600, 249700, 261100, 272800, 284800,
        297100, 309700, 322600, 335800, 349300, 363100, 377200, 391600, 406300, 421300,
        436600, 452200, 468100, 484300, 500800, 517600, 534700, 552100, 569800, 587800,
        606100, 624700, 643600, 662800, 682300, 702100, 722200, 742600, 763300, 784300,
        805600, 827200, 849100, 871300, 893800, 916600, 939700, 963100, 986800, 1010800,
        1035100, 1059700, 1084600, 1109800, 1135300, 1161100, 1187200, 1213600, 1240300, 1267300,
        1294600, 1322200, 1350100, 1378300, 1406800, 1435600, 1464700, 1494100, 1523800, 1553800,
        1584100, 1614700, 1645600, 1676800, 1708300, 1740100, 1772200, 1804600, 1837300, 1870300,
        1903600, 1937200, 1971100, 2005300, 2039800, 2074600, 2109700, 2145100, 2180800, 2216800,
        2253100, 2289700, 2326600, 2363800, 2401300, 2439100, 2477200, 2515600, 2554300, 2593300,
        2632600, 2672200, 2712100, 2752300, 2792800, 2833600, 2874700, 2916100, 2957800, 2999800,
    ];

    /** Rarity → starting level offset in PET_LEVELS array. */
    private const PET_RARITY_OFFSET = [
        'COMMON'    => 0,
        'UNCOMMON'  => 6,
        'RARE'      => 11,
        'EPIC'      => 16,
        'LEGENDARY' => 20,
        'MYTHIC'    => 20,
    ];

    /** Pet type → head texture hash (from SkyCrypt PET_DATA). */
    private const PET_HEAD_TEXTURES = [
        'AMMONITE' => 'a074a7bd976fe6aba1624161793be547d54c835cf422243a851ba09d1e650553',
        'ANKYLOSAURUS' => 'c1aa836b9096c417903299a6c5ab41738c19648ac439fed4bcbe6c32605338dc',
        'ARMADILLO' => 'c1eb6df4736ae24dd12a3d00f91e6e3aa7ade6bbefb0978afef2f0f92461018f',
        'BABY_YETI' => 'ab126814fc3fa846dad934c349628a7a1de5b415021a03ef4211d62514d5',
        'BAL' => 'c469ba2047122e0a2de3c7437ad3dd5d31f1ac2d27abde9f8841e1d92a8c5b75',
        'BAT' => '382fc3f71b41769376a9e92fe3adbaac3772b999b219c9d6b4680ba9983e527',
        'BEE' => '7e941987e825a24ea7baafab9819344b6c247c75c54a691987cd296bc163c263',
        'BINGO' => 'd4cd9c707c7092d4759fe2b2b6a713215b6e39919ec4e7afb1ae2b6f8576674c',
        'BLACK_CAT' => 'e4b45cbaa19fe3d68c856cd3846c03b5f59de81a480eec921ab4fa3cd81317',
        'BLAZE' => 'b78ef2e4cf2c41a2d14bfde9caff10219f5b1bf5b35a49eb51c6467882cb5f0',
        'BLUE_WHALE' => 'dab779bbccc849f88273d844e8ca2f3a67a1699cb216c0a11b44326ce2cc20',
        'BUMBLEBEE' => '7e941987e825a24ea7baafab9819344b6c247c75c54a691987cd296bc163c263',
        'CHICKEN' => '7f37d524c3eed171ce149887ea1dee4ed399904727d521865688ece3bac75e',
        'DOLPHIN' => 'cefe7d803a45aa2af1993df2544a28df849a762663719bfefc58bf389ab7f5',
        'DROPLET_WISP' => 'b412e70375ec99ee38ae94b30e9b10752d459662b54794dfe66fe6a183c672d3',
        'EERIE' => 'c3af70c6ff76ba48f24ee8a2063a5b50bbfabf409f4795248a292f8289f47c98',
        'ELEPHANT' => '7071a76f669db5ed6d32b48bb2dba55d5317d7f45225cb3267ec435cfa514',
        'ENDER_DRAGON' => 'aec3ff563290b13ff3bcc36898af7eaa988b6cc18dc254147f58374afe9b21b9',
        'ENDERMAN' => '6eab75eaa5c9f2c43a0d23cfdce35f4df632e9815001850377385f7b2f039ce1',
        'ENDERMITE' => '5a1a0831aa03afb4212adcbb24e5dfaa7f476a1173fce259ef75a85855',
        'FLYING_FISH' => '40cd71fbbbbb66c7baf7881f415c64fa84f6504958a57ccdb8589252647ea',
        'FRACTURED_MONTEZUMA_SOUL' => 'df656c06e8a5cb4692564ee21748bddec9d785d1834284aaa1439601bba47d6b',
        'FROST_WISP' => '1d8ad9936d758c5ea30b0b7cc7c67c2bfcea829ecf2425c0b50fc92a26ae23d0',
        'GHOUL' => '87934565bf522f6f4726cdfe127137be11d37c310db34d8c70253392b5ff5b',
        'GIRAFFE' => '176b4e390f2ecdb8a78dc611789ca0af1e7e09229319c3a7aa8209b63b9',
        'GLACIAL_WISP' => '3e2018feebe1a99177b3cb196d4e44521268b4b3eb56e6419cb0253cdbf0456c',
        'GLACITE_GOLEM' => 'af132a6593876d3c377d503fd66eca3fb938743251f7b16a9870c60b7388c8a3',
        'GOBLIN' => '7309d8dc35a638a04b915a3b15a1452ceeae0d7ea42bcdadb21b03046987515c',
        'GOLDEN_DRAGON' => '2e9f9b1fc014166cb46a093e5349b2bf6edd201b680d62e48dbf3af9b0459116',
        'GOLEM' => '89091d79ea0f59ef7ef94d7bba6e5f17f2f7d4572c44f90f76c4819a714',
        'GRANDMA_WOLF' => '4e794274c1bb197ad306540286a7aa952974f5661bccf2b725424f6ed79c7884',
        'GRIFFIN' => '4c27e3cb52a64968e60c861ef1ab84e0a0cb5f07be103ac78da67761731f00c8',
        'GUARDIAN' => '221025434045bda7025b3e514b316a4b770c6faa4ba9adb4be3809526db77f9d',
        'HORSE' => '36fcd3ec3bc84bafb4123ea479471f9d2f42d8fb9c5f11cf5f4e0d93226',
        'HOUND' => 'b7c8bef6beb77e29af8627ecdc38d86aa2fea7ccd163dc73c00f9f258f9a1457',
        'JERRY' => '822d8e751c8f2fd4c8942c44bdb2f5ca4d8ae8e575ed3eb34c18a86e93b',
        'JELLYFISH' => '913f086ccb56323f238ba3489ff2a1a34c0fdceeafc483acff0e5488cfd6c2f1',
        'KUUDRA' => '1f0239fb498e5907ede12ab32629ee95f0064574a9ffdff9fc3a1c8e2ec17587',
        'LION' => '38ff473bd52b4db2c06f1ac87fe1367bce7574fac330ffac7956229f82efba1',
        'MAGMA_CUBE' => '38957d5023c937c4c41aa2412d43410bda23cf79a9f6ab36b76fef2d7c429',
        'MAMMOTH' => '6b10715732cd1fd49fa1b6187947c307dd4687105cf033840607f9d6234743ad',
        'MEGALODON' => 'a94ae433b301c7fb7c68cba625b0bd36b0b14190f20e34a7c8ee0d9de06d53b9',
        'MITHRIL_GOLEM' => 'c1b2dfe8ed5dffc5b1687bc1c249c39de2d8a6c3d90305c95f6d1a1a330a0b1',
        'MOLE' => '727baaafc09978d4bda73e16afdde85ec13b0f95ad989524c5fcaa717cf06b4a',
        'MONKEY' => '13cf8db84807c471d7c6922302261ac1b5a179f96d1191156ecf3e1b1d3ca',
        'MOOSHROOM_COW' => '2b52841f2fd589e0bc84cbabf9e1c27cb70cac98f8d6b3dd065e55a4dcb70d77',
        'OCELOT' => '5657cd5c2989ff97570fec4ddcdc6926a68a3393250c1be1f0b114a1db1',
        'OWL' => 'da3216da54e7368fb40b721239ad95e07ef4f97d93f1c42ff319bab9a53882af',
        'PARROT' => '5df4b3401a4d06ad66ac8b5c4d189618ae617f9c143071c8ac39a563cf4e4208',
        'PENGUIN' => '37534e97f36e5a8335928e171ec99608bee7fb16e260afb301025b3b17eeefc4',
        'PHOENIX' => '23aaf7b1a778949696cb99d4f04ad1aa518ceee256c72e5ed65bfa5c2d88d9e',
        'PIG' => '621668ef7cb79dd9c22ce3d1f3f4cb6e2559893b6df4a469514e667c16aa4',
        'PIGMAN' => '63d9cb6513f2072e5d4e426d70a5557bc398554c880d4e7b7ec8ef4945eb02f2',
        'RABBIT' => '117bffc1972acd7f3b4a8f43b5b6c7534695b8fd62677e0306b2831574b',
        'RAT' => 'a8abb471db0ab78703011979dc8b40798a941f3a4dec3ec61cbeec2af8cffe8',
        'REINDEER' => 'a2df65c6fd19a58bee38252192ac7ce2cf1dc8632c3547a9228b6b697240d098',
        'RIFT_FERRET' => 'b6b11399448260185da1d17e54c984515faab6d8585f00972451ec2b43d46f94',
        'ROCK' => 'cb2b5d48e57577563aca31735519cb622219bc058b1f34648b67b8e71bc0fa',
        'SCATHA' => 'df03ad96092f3f789902436709cdf69de6b727c121b3c2daef9ffa1ccaed186c',
        'SHEEP' => '64e22a46047d272e89a1cfa13e9734b7e12827e235c2012c1a95962874da0',
        'SILVERFISH' => 'da91dab8391af5fda54acd2c0b18fbd819b865e1a8f1d623813fa761e924540',
        'SKELETON' => 'fca445749251bdd898fb83f667844e38a1dff79a1529f79a42447a0599310ea4',
        'SKELETON_HORSE' => '47effce35132c86ff72bcae77dfbb1d22587e94df3cbc2570ed17cf8973a',
        'SLUG' => '7a79d0fd677b54530961117ef84adc206e2cc5045c1344d61d776bf8ac2fe1ba',
        'SNAIL' => '50a9933a3b10489d38f6950c4e628bfcf9f7a27f8d84666f04f14d5374252972',
        'SNOWMAN' => '11136616d8c4a87a54ce78a97b551610c2b2c8f6d410bc38b858f974b113b208',
        'SPIDER' => 'cd541541daaff50896cd258bdbdd4cf80c3ba816735726078bfe393927e57f1',
        'SPINOSAURUS' => 'd3c9d479471a2f13f22548315159591720992e70c920fef83a901b7186720e3c',
        'SPIRIT' => '8d9ccc670677d0cebaad4058d6aaf9acfab09abea5d86379a059902f2fe22655',
        'SQUID' => '01433be242366af126da434b8735df1eb5b3cb2cede39145974e9c483607bac',
        'SUBZERO_WISP' => '7a0eb37e58c942eca4d33ab44e26eb1910c783788510b0a53b6f4d18881e237e',
        'TARANTULA' => '8300986ed0a04ea79904f6ae53f49ed3a0ff5b1df62bba622ecbd3777f156df8',
        'TIGER' => 'fc42638744922b5fcf62cd9bf27eeab91b2e72d6c70e86cc5aa3883993e9d84',
        'TURTLE' => '212b58c841b394863dbcc54de1c2ad2648af8f03e648988c1f9cef0bc20ee23c',
        'TYRANNOSAURUS' => '93f28ec96df59c67e9d2fc2e7e3d055fa31646e4111add9fe26a692801964126',
        'WITHER_SKELETON' => 'f5ec964645a8efac76be2f160d7c9956362f32b6517390c59c3085034f050cff',
        'WOLF' => 'dc3dd984bb659849bd52994046964c22725f717e986b12d548fd169367d494',
        'ZOMBIE' => '56fc854bb84cf4b7697297973e02b79bc10698460b51a639c60e5e417734e11',
    ];

    /** Cosmetic pet skin overrides – maps API skin value → head texture hash. */
    private const PET_SKINS = [
        'ENDERMAN' => 'ea84cc8818c293484fdaafc8fa2f0bf39e55733a247d68023df2c6c6b9b671d0',
        'ENDERMAN_SLAYER' => '8fff41e1afc597b14f77b8e44e2a134dabe161a1526ade80e6290f2df331dc11',
        'GUARDIAN' => '37cc76e7af29f5f3fbfd6ece794160811eff96f753459fa61d7ad176a064e3c5',
        'TIGER_TWILIGHT' => '896211dc599368dbd9056c0116ab61063991db793be93066a858eb4e9ce56438',
        'RABBIT' => 'a34631d940fddb689ddef6a3b352c50220c460dba05cd18dc83192b59dc647f8',
        'RABBIT_AQUAMARINE' => '35a2119d122961852c010c1007ab2aff95b4bbeb74407463f6d2e1ff0792c812',
        'RABBIT_ROSE' => 'd7cddf5b20cb50d6600e5333c6bb3fb15b4741f17e3675fc2bfc09c2cd09e619',
        'WITHER' => '224c2d14a0219af5ccfcaa36e8a333e271724ed61276611f9529e16c10273a0d',
        'ROCK_COOL' => 'fefcdbb7d95502acc1ae35a32a40ce4dec8f4c9f0da26c9d9fe7c2c3eb748f6',
        'ROCK_SMILE' => '713c8b2916a275db4c1762cf5f13d7b95b91d60baf5164a447d6efa7704cf11b',
        'ROCK_THINKING' => 'dd2f781f03c365bbc5dd1e7186ab38dc69465e836c9fe066a9a844f34a4da92',
        'ROCK_LAUGH' => '8cc1ef513d5f616675242174acde7b9d6259a47c4fe8f6e4b6e20920319d7073',
        'ROCK_DERP' => 'c4f89fbd12c209f7f26c1f34a1bd7f47635814759c09688dd212b205c73a8c02',
        'ROCK_EMBARRASSED' => '27ff34992e66599e8529008be3fb577cb0ab545294253e25a0cc988e416c849',
        'SHEEP_WHITE' => 'b92a1a5c325f25f7438a0abb4f86ba6cf75552d02c7349a7292981459b31d2f7',
        'SHEEP_PURPLE' => '99a88cf7dd33063587c6b540e6130abc5d07f1a65c47573ab3c1ad3ccec8857f',
        'SHEEP_BLACK' => 'aa9dcda642a807cd2daa4aa6be87cef96e08a8c8f5cec2657dda4266c6a884c2',
        'SHEEP_PINK' => 'afa7747684dcb96192d90342cea62742ec363da07cb5e6e25eecec888cd2076',
        'SHEEP_LIGHT_BLUE' => '722220de1a863bc5d9b9e7a6a3b03214c9f3d698ed3fe0d28220f3b93b7685c5',
        'SHEEP_LIGHT_GREEN' => 'cf183ec2fe58faa43e568419b7a0dc446ece4ea0be52ec784c94e1d74b75939d',
        'SHEEP_NEON_YELLOW' => '94263428c23da9165b2639a8f2428ff4835227945c9e1038461cf644d67cc82a',
        'SHEEP_NEON_RED' => '4918be142a20b2b39bc582f421f6ae87b3184b5c9523d16fbe6d69530107886a',
        'SHEEP_NEON_BLUE' => 'e55b3fe9311c99342ea565483cbf9e969a258faf7afa30270fb9a0929377acfd',
        'SHEEP_NEON_GREEN' => '2c14d66911554bd0882339074bf6b8110c2d3509b69e7a6144e4d5a7164bacc8',
        'SILVERFISH' => 'd8552ff591042c4a38f8ba0626784ae28c4545a97d423fd9037c341035593273',
        'SILVERFISH_FOSSILIZED' => 'ca3a363368ed1e06cee3900717f062e02ec39aee1747675392255b48f7f83600',
        'ELEPHANT_PINK' => '570eef474ec0e56cc34c2307eaa39f024612f8cd7248e7d5b14169ebd307c742',
        'ELEPHANT_BLUE' => '4b62969c005815d0409136380febc5ac468aaba9bda4db80954fa5426ee0a323',
        'ELEPHANT_ORANGE' => '554a34a80c474206d3700b8fced6b44fab0b0ed0b05c1293ff0c5d86eda251d1',
        'ELEPHANT_RED' => 'ba5c66ec66cb6b4b5550085f583b4e5c1cee5247bec5fbcc5c318c30c66cab42',
        'ELEPHANT_PURPLE' => '5ff9df290b6c5a4984fc6e516605f9816b9882f7bf04db08d3f7ee32d1969a44',
        'ELEPHANT_GREEN' => '360c122ade5b2fedca14aa78c834a7b0ac9cb5da2a0c93112163086f90c13b68',
        'ELEPHANT_MONOCHROME' => '4bdf0f628c05e86cabdee2f5858dd5def7f8b8d940cbf25f9937e2ffb53432f4',
        'JERRY_RED_ELF' => '1d82f9c36e824c1e37963a849bf5abd76d3b349125023504af58369086089ee9',
        'JERRY_GREEN_ELF' => '4ec5455f43426ca1874b5c7b4a492ec3722a502f8b9599e758e133fed8b3c1e4',
        'YETI_GROWN_UP' => 'f5f29a975529276d916fc67998833c11ee178ff21e5941afdfb0fa7010f8374e',
        'MONKEY_GOLDEN' => 'e9281c4d87d68526b0749d4361e6ef786c8a35717aa053da704b1d53410d37a6',
        'MONKEY_GORILLA' => 'c3eb3e37e9873bfc176b9ed8ef4fbef833de144546bfaefdf24863c3eb87bb86',
        'HORSE_ZOMBIE' => '578211e1b4d99d1c7bfda4838e48fc884c3eae376f58d932bc2f78b0a919f8e7',
        'DRAGON_NEON_BLUE' => '96a4b9fbcf8c3e7e1232e57d6a2870ba3ea30f76407ae1197fd52e9f76ca46ac',
        'DRAGON_NEON_PURPLE' => '54bdf5ba6289b29e27c57db1ec7f76151c39492d409268e00a9838e8c963159',
        'DRAGON_NEON_RED' => 'e05c9b4f4218677c5b4bcc9c7d9e29e18d1684a536781fede1280fc5e6961538',
        'DRAGON_PASTEL' => '4a62ec4e019fe0fed059663ae59daa0d91729517bf33ae7f7d7e722913602df4',
        'WHALE_ORCA' => 'b008ca9c00cecf499685030e8ef0c230a32908619ce9dc10690b69111591faa1',
        'CHICKEN_BABY_CHICK' => '1bde55ed54cb5c87661b86c349186a9d5baffb3cb934b449a2d329e399d34bf',
        'BLACK_CAT_IVORY' => 'f51b17d7ded6c7e8f3b2dac12378a6fc4e9228b911986f64c8af45837ae6d9e1',
        'BLACK_CAT_ONYX' => 'be924115d3a8bbacfd4fafb6cc70f99a2f7580e4583a50fa9b9c285a98ac0c56',
        'ENDERMITE_RADIANT' => '2fc4a7542b754420b1b19f9a28ea00040555a9e876052b97f65840308a93348d',
        'WOLF' => 'c8e414e762e1024c799e70b7a527c22fb95648f141d660b10c512cc124334218',
        'HOUND_BEAGLE' => '877364e0ce27f0239b7754706b93022d0cf945854015d6096f9cf43d24a38269',
        'SQUID_GLOW' => 'fca9982520eee4066bab0ae697f3b3656084b6639ba89113bd8e23ab7288563d',
        'TIGER_SABER_TOOTH' => 'e92dba2fbd699d541b2fa0fbcaff640ad8c311987ade59a13b2a65d0ce319316',
        'PARROT_GOLD_MACAW' => '5dad34650f8d1c6afbfd979b38d7e1412e636215b8f85240e06d998278879b8b',
        'BAT_VAMPIRE' => '473af69ed9bf67e2f5403dd7d28bbe32034749bbfb635ac1789a412053cdcbf0',
        'PHOENIX_ICE' => '12582057e52d0f7fffd1a1f93acf196db5f09b76f1ba3ede28476cc4cd82da97',
        'OCELOT_SNOW_TIGER' => '496499b99c88314b1459fc5b515c477b069bf2229a2833abb2e1ff20b5f29457',
        'BLAZE_FROZEN' => '9617a34c8ff467fdb45be3ff17863fcff7e8424c8dd9b99666edd13b44b32e8c',
        'DOLPHIN_SNUBNOSE_GREEN' => '5f2879bd8b0bafdd71dbd3fc5850afc6c53da60d4252182cfc80737a00d72408',
        'DOLPHIN_SNUBNOSE_RED' => '779df5b4da325c0d740251b4204a0cd22d9fdb88cecb6eff6176ef4f2ecedb1e',
        'DOLPHIN_SNUBNOSE_PURPLE' => 'fd0b213c15dd7b8c67512bc18bf14d32dc4b57b9c305d1c7514aa3e2609a78a4',
        'DOLPHIN_SNUBFIN' => '279413c788c7f450234bdab0cf0d0291c57f730e380c6d4c7746fde15928381',
        'TIGER_GOLDEN' => 'c85f8db6e5b826d3dd5847cd8d7279f4d4dd50bc955ca7968c7c49b496ed7a3b',
        'ARMADILLO_ENCHANTED' => '7426d7b174e8bd9c283f91a42cf2dfa95a518d5eae97ab5595412d4951d4db18',
        'ARMADILLO_SEAFOAM' => 'd0c72b0db2ecbdaf153c563593d17d546b302b278b1b81d3e063963b5b0e5bc4',
        'JERRY_HANDSOME' => '11be7e0da38de93dba068a40011808ecc39bb757d3fdee8fb25128e2a06dde86',
        'KUUDRA_LOYALTY' => 'bb7d06ab10c4d15433670ca59ed6ad87d797c24bf7bfc3343730aa1594a4970c',
        'ENDERMAN_NEON' => '6f9020c07d875bad1440337adb55a08c15db06b994646a691795f4cd293fe3de',
        'ENDERMAN_XENON' => '92defbe3cde326d4511bb53339d777afa703f3ec4daa697d61a4402744cbb0cd',
        'BAL_INFERNO' => '15ae6e347c2c828020b22f6faed62baf27548fad1656447af007b802d6f556e4',
        'MEGALODON_BABY' => '7cdb1ff8c967c831eb685e09e5dea586ed291472395acf7a25e9d5bb41d6c082',
        'ENDER_DRAGON_UNDEAD' => 'e7232b2d2e618076cd620e352445d4a16382fdb24bcfafba7acceec0c146d2f6',
        'BEE_RGBEE' => '99c6da45da5a46614c05d5f8d7b3dfefb025e82ca4cdb4f0db64ced1315df659',
        'JELLYFISH_LUMINESCENT' => 'fb656bac64ada64fa221e9f48fbf8ab9334b7934efb979d758df4d0c1bd17695',
        'ROCK_SUS' => '54f2672f949d435c2cda44367b0f08a6ca25ea7f46a50b66f28840ad838c858',
        'ROCK_SWEATING' => '26ca4c9f9543238de127a0af424e2e0dcfe7ef26fbd21c9a026adac2caa1fe04',
        'ROCK_SURPRISED' => '8c7c4545d4134fae44ef319303c91ad14fcc9354b0bf071933b4a420e172fbd1',
        'ROCK_BLINKING' => 'f814eadaa5da6e38f8536501d457a130adef9a025103bdd19dd4732d353509a0',
        'GOLDEN_DRAGON_ANUBIS' => 'b44df25075a320c68df0e9bc76fd7f0db0be6f128e59d14f0591fed5cc6c396e',
        'BLACK_CAT_PURRANORMAL' => '534a1340f5aa4f013bd49e9d41d8a471d3a044165adcb929c05fe052376e5e4b',
        'BEE_ZOMBEE' => 'f6e1ae93ad5e94a0904a0877ff14a9de56083242a5786ac7dc503e098ae37120',
        'RAT_HIDE_AND_SQUEAK' => 'c484a612ef0b1061478ddd7595abd612fb61de5a3cdc7d0e53c5f6915f0ebb8f',
        'SHEEP_CHROMA_WOOLY' => '53911510276c27656be97386baabb2d8543c0dffb9e5723ad279438fc2eaa51e',
        'SHEEP_BLACK_WOOLY' => 'dd7d6f438d16594c46b1fc3c868e4b68c53324ad01bcfe1a22052fc822cb5bb1',
        'SHEEP_WHITE_WOOLY' => '7b44fb8a08057e4a7278a6c5aa2cb692f2e7cfea960c68fc0d4d2e26e88dd359',
        'MOOSHROOM_COW_MOOCELIUM' => 'b92d94a5efca94f8739973b44a01bbbdf4a2c0e05dbb45e97342076c950b73a7',
        'SNOWMAN_ICE_GOLEM' => '69736cfd666c4237e910892d8974889e199c2d59b7d006dcaa01b6241393fe23',
        'CHICKEN_TURKEY' => 'a36c5153d00dd7356eb9aa438e9f1fe668ce3e26498793ed33c13908955b95e4',
        'TIGER_NEON' => '805de32806ea868cb6e222656936932f106fb4f44018afdbf78e4566d5e0a34b',
        'ROCK_NORTH_STAR' => '45f5cab199508725ea2bbe3ed2d84b891c5d8b7ee7eaedc69e67196dc6fee3d9',
        'GRIFFIN_REINDRAKE' => '70beb4c7f5f8d14be3a04f6fa3573fa8bd59ea2236b66f05fba57c678ca37776',
        'REINDEER_AURORA' => '39f88de3aa15a64da4fe190ae1d674cfcafd4fbf4c29855c87671bd741625cc6',
        'RABBIT_LUNAR' => '71e3f67fb39b2a1e01426aa091c5a6666b237286eb1c4c5fcbca505dfe1dbdc3',
        'SCATHA_ALBINO' => 'd608fdc7c9c2fbb51fc7bbfca8874752d9435875d782654b4f1d2b2220e9d6ae',
        'SCATHA_GOLDEN' => '4313bb28c6e2cfe4444a3eaacab53103cc91f9419015292facfd431e950bf71a',
        'SCATHA_DARK' => '9b6946ddfdcdc5a894faa8f1b048728521dc0f93c751d6980f85b98a504a1789',
        'AMMONITE_MAGMA' => '7008b8d08f812efe328f898aa7ccf70a68c7dded3ba715490e25065e7ff34d13',
        'AMMONITE_NATURAL' => 'f1546d6a690ecf22a5a93812435ddacbbe9c278a02ea38039d938313344dba53',
        'WHALE_COSMIC' => '1a68f59a187f5b7183df2ea708a6e4704845afd433272cd32cbed52286db1fd3',
        'MONKEY_LEMUR' => 'b6f45eb8a8bef3f8ee406ac0923d8626c5e5b7ff511cfbd417c994b68941f104',
        'PARROT_BUCCANEER' => '385de4d50ab148f68fa377cdd760f9d23f20d503b3e1c1ad74120029ccdc7299',
        'ENDER_DRAGON_BABY_BLUE' => '49582fa21c45c40a80a9a791842f71078f47c335d627bb560eb86dad641a8c4',
        'ENDER_DRAGON_BABY' => '24e73adc6be7922d0d6287192b48b4afcd6d72207773d0b15a1903b051ec0c22',
        'TURTLE_ANCIENT' => '5a85b6d364e8cd9dc777c0beed34e1af28d1bd6777a93852073c142a8ae17586',
        'TURTLE_ANCIENT_GOLDEN' => 'b972da505e9e55e32039c5d8f5dbb2f12ab52eea02bec3df6a40adb8b8fecbb0',
        'BABY_YETI_DARK_SASQUATCH' => 'e8aebb0944fbfaa30ee685684abe2340a532dd21da7e364adb6e839f06466adc',
        'BABY_YETI_MIDNIGHT' => '128aa536588508afb2648253edee46849f308165d382f8ab883bf6de4a135d39',
        'BABY_YETI_LIGHT_SASQUATCH' => '128aa536588508afb2648253edee46849f308165d382f8ab883bf6de4a135d39',
        'ENDERMITE_DYNAMITE' => '9ebfdace31ac59cdd397869ac4471d8c7ddb4b4d6fa5e9928bbda45f092230d0',
        'WOLF_HUSKY' => '7a558f634d802974dc6ab7e1a4aa72ade63c356268ef2e512f4c09d050e6cf0b',
        'BAT_CANDY' => '44a22f845e588a1380f429e4d98732453995bebbefa1ba79b5134ca70ad78995',
        'BAT_BONE' => '2444eb6f238a00528a7f9bfbdcd6c6b0bac8bdb1e3749ef4f0931013c78b8804',
        'RAT_GYM_RAT' => 'e9f05d0552f5418957808d3b098567e9d6294e5257f6c677f22f3401c9616231',
        'RAT_JUNK_RAT' => '879d59e8cff4831c985988f5701a6689a88ccbcb094a97b61bd408384d093417',
        'RAT_KARATE' => 'c0983ff7f3e7525f32fceab07b64aced104e4c8798294a1dcc262e5283327258',
        'RAT_MR_CLAWS' => '11787e838120c6cce0570154a406adeefec3f04afdaec915067db829b3588270',
        'RAT_NINJA' => '337e9a838503d17acdb0d2642e5bcdefa0393887b61bb13698f4368333a6e962',
        'RAT_PIRATE' => 'd7c7624fc4463749ed516df1bbbaa67c172ffc9f20b6f2d5b80e39ccf03d5f7a',
        'RAT_RAT-STRONAUT' => '22f6f61f863dea14b18ee763d0cc0053bad338ed7755cc52df459c6c9a10a237',
        'RAT_SECRAT_SERVICE' => '3d248f8e7cb791ece9bcea2dd0381f2eb138f18f02b9657099625735cd4bfc0e',
        'RAT_SECURATY_GUARD' => 'e0da7bef0d3dda928ad34d6f7ca780e08659af043bdb31a09e123ccf6b63ad3c',
        'RAT_SQUEAKHEART' => '278ca13dc36d2efbc628a8f0265325e589a56475cc6cde1f267e7ed083f1f8ff',
        'ENDERMAN_SUN' => '7f261e3fe875dd75a7fbd4ea7b1932ca2f3e18665a80f32429e4f3fe504dca76',
        'ENDERMAN_NEBULA' => 'b32907495cfb0cf38bb0cdd2cd1822111cb6469088cd98423143815b55dbbd4a',
        'ENDERMAN_BLUE_MOON' => '181586f6e79169d79570e319b2f08895cf366ca3ac506af88f831510783f8987',
        'CHICKEN_RUBBER' => '414b3d49dfaf37ce453deb2cafb67dd4b4701195bacc0b6d9a38ac32e34a3eb2',
        'SLUG_AZURE_SEA_SLUG' => '164e899de34b54cc75cf0959067678d2c4173f1da0fc3f8ba1384b6a58a6d847',
        'SLUG_LEAF_SHEEP_SEA_SLUG' => 'c55803ea21a24393b5f5e16b72121e658c67696ffa599b13d8127a7fef5183b',
        'SLUG_VIOLET_SEA_SLUG' => 'd1ded62405522ec72b8c4ef306e1098b3ede765d971d574ffa03c0b099f803a6',
        'SNAIL_RAINBOW' => 'e95522a1aabe6709ee077de132ae1e501b84e98d5bb0d531ca3d637c121297f6',
        'CAT_SPACE_KITTY' => 'c6f8dfc25e70cc036774e77253349c548a10f232a999b7287a84b2f9b5c6c4a8',
        'WOLF_DOGE' => '8e19ac0af105a22640b764815687bf069336ede7c63c0c9fbb843e22d172d0b1',
        'ZOMBIE_SENTINEL' => '2422c02fe18d29ed2e3554febab7ef4e3f17c85a6cc379aec5d89aec233b78cb',
        'ZOMBIE_BRAIN_FREEZE' => 'b1abfeefe236b3c9a23a71b30d0c7892133423d6b40bfa21e8d8d66cde8d8e19',
        'ZOMBIE_BIG_BRAIN' => '3871a02f09137806064c3e90d9bb8e0346fe73a392679da0a6584c605b0724fb',
        'BABY_YETI_PLUSHIE' => 'f0ef518cb67739c4af18cdfffa05360079afc735ef291528fa7ae0c926804c59',
        'SNOWMAN_PLUSHIE' => '316cc8f6d7d3b211dac2dc87bd7779e1ff8a001db99c6c77e679a85a669b1441',
        'REINDEER_PLUSHIE' => '6c4a5ce9a490eae59b8597cd171ac802978f3eebf1679259d3e0667f4ae29d00',
        'ELEPHANT_BLACK_PLUSHIE' => 'e69e45deda0db5a91e6b8aac2765c605509170e1cfc3500b0d757e630e4ab247',
        'ELEPHANT_GRAY_PLUSHIE' => '3f123b8de86123ef2bf01d84bc97d258205eb99df95a5a85e8dcfaac8068d4f1',
        'ELEPHANT_BLUE_PLUSHIE' => '20226fa7c2569ea7569033b3a11043d25213955f9f792e517fb75fc405d4223a',
        'ELEPHANT_COSMIC' => 'ed1a8896ab5fb9ede183df9938f24f596ac23f73fe6205749250bddfdb103f03',
        'ELEPHANT_PINK_PLUSHIE' => '7d1fd8b2236a5285057774de0c0b748453aa5fbf85219dab726b333a9d86664a',
        'MONKEY_SEE_HEAR_SPEAK' => 'a6b856d923ca6f40013832af51370196db541b8c5209f4ea80e2eadf658d7949',
        'GIRAFFE_SAFARI' => '16474dfc3fb1c5932b33d2b85adc840f1dcaa8c5b84bf6e32b35bba1302885f7',
        'MONKEY_ASTRONAUT' => 'f7e0a9ab4fb38db15bb958a6b2cf852893f62d0395f55a084d8e28c8d5b48e7e',
        'OCELOT_JUNGLE' => 'e25101f8531b1207d607597d3b87d7c65b990fb97d918c0dbddcf16f5f713f7f',
        'MITHRIL_GOLEM_CRYSTAL' => 'a20aa3dfd94a622bbe4facf0b19e6dbb07636e7762ade427ccaa2f94a075326c',
        'MITHRIL_GOLEM_CHERRY_BLOSSOM' => '8d8e9ab5c4c8ed7a9308fb68b0cb40cdae0cedc58eff21cf00b560a60365d6d5',
        'MITHRIL_GOLEM_COPPER' => 'bdc264745689b75ff1ce396479fde465f3a622ab1e9e013a3e448682ad9095b2',
        'GOLDEN_DRAGON_ANCIENT' => 'b761ef38b2196eb057be70791367b7b380e3bfd7494517399f96cc10d27e509e',
        'BEE_BEEDAZZLED' => '66671fa36a56cd08c85f0956fca5ffa3793d744a373244b92b71a55da5636c0f',
        'BEE_BEELOVED' => 'fb2e99137ba2a2996ec45e806550d617da479a0a48846b5f8bbc81b6ab59dd2f',
        'BEE_GREAT_GATSBEE' => 'a1235c3dececec89f37f25abbefa227ec3a4fe73e03be9361ed473301b4e0d7b',
        'BEE_HIVE' => '8ac223e6e77dad17bb3aa0909556f651776f81d4c760c7167b2e27c49c4fc59',
        'BEE_HONEY' => '36e7a813e27580e0b5cade870905b8c8c7cbc178f80893384c8697baeb8a6a8',
        'BEE_RUGBEE' => '32eab2723eed1866b234b4ab967ee6392674d76dd7508b94640311ed1fdb36e5',
        'BEE_SPELLING_BEE' => '9a09e730872732d3d267120a4a1ba732ab224ee96215e80bbec78af4dcfdd2a1',
        'BEE_USB' => 'ad8e4187921f223878af435d4554c32473e7437f59236618c80a0cd773b9606d',
        'BEE_WANNA_BEE' => '61bbdb3a28ec3ec7e562c82a2851c15f26452d81043bca1747db9b0c91b43210',
        'BEE_WASABEE' => 'c7887fb09860985d5bafaae88c706ec9b190a03c1a7501b5ba34a32950542c17',
        'JERRY_LEPRECHAUN' => '4f71c743ce4c945dcfd942e3b5f8747e32c7ede6041c1f5fa71ba9eac1e71818',
        'TARANTULA_PINK' => 'e841ad77a748ed3d1d50855d1749981d5f38f8dbf55c14f74dd4235766181e3b',
        'TARANTULA_GREENBOTTLE' => 'b6f03e15ccdf4b43016959efd357e61a77e98ab4a18b006cfdb209c5cfb99457',
        'SPIDER_BLACK_WIDOW' => '4ef27331cb9b93ec33be5ff388900d2666967ca2712c29057de9fa702a84b87a',
        'SPIDER_PEACOCK' => 'f9c75185cf50c6680b98bd25d99820fc238bfdbc8d8c694cef207a5ac84ee1e3',
        'MAGMA_CUBE_CHOCO_CUBE' => 'e2d1620300a8e1e0bda70c71efbc46b7845d6171584d800fa9004cfad88d2aa8',
        'RABBIT_PLUSHIE' => 'a15683eaca0913279361a1f8baa461a90f1cf0193df938f81507b0d84f9fb37e',
    ];

    /** Pet held item display names and tiers. */
    private const PET_ITEMS = [
        'PET_ITEM_ALL_SKILLS_BOOST_COMMON' => ['name' => 'All Skills Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_BIG_TEETH_COMMON' => ['name' => 'Big Teeth', 'tier' => 'COMMON'],
        'PET_ITEM_IRON_CLAWS_COMMON' => ['name' => 'Iron Claws', 'tier' => 'COMMON'],
        'PET_ITEM_SHARPENED_CLAWS_UNCOMMON' => ['name' => 'Sharpened Claws', 'tier' => 'UNCOMMON'],
        'PET_ITEM_HARDENED_SCALES_UNCOMMON' => ['name' => 'Hardened Scales', 'tier' => 'UNCOMMON'],
        'PET_ITEM_BUBBLEGUM' => ['name' => 'Bubblegum', 'tier' => 'RARE'],
        'PET_ITEM_LUCKY_CLOVER' => ['name' => 'Lucky Clover', 'tier' => 'EPIC'],
        'PET_ITEM_TEXTBOOK' => ['name' => 'Textbook', 'tier' => 'LEGENDARY'],
        'PET_ITEM_SADDLE' => ['name' => 'Saddle', 'tier' => 'UNCOMMON'],
        'PET_ITEM_EXP_SHARE' => ['name' => 'Exp Share', 'tier' => 'EPIC'],
        'PET_ITEM_TIER_BOOST' => ['name' => 'Tier Boost', 'tier' => 'LEGENDARY'],
        'PET_ITEM_COMBAT_SKILL_BOOST_COMMON' => ['name' => 'Combat Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_COMBAT_SKILL_BOOST_UNCOMMON' => ['name' => 'Combat Exp Boost', 'tier' => 'UNCOMMON'],
        'PET_ITEM_COMBAT_SKILL_BOOST_RARE' => ['name' => 'Combat Exp Boost', 'tier' => 'RARE'],
        'PET_ITEM_COMBAT_SKILL_BOOST_EPIC' => ['name' => 'Combat Exp Boost', 'tier' => 'EPIC'],
        'PET_ITEM_FISHING_SKILL_BOOST_COMMON' => ['name' => 'Fishing Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_FISHING_SKILL_BOOST_UNCOMMON' => ['name' => 'Fishing Exp Boost', 'tier' => 'UNCOMMON'],
        'PET_ITEM_FISHING_SKILL_BOOST_RARE' => ['name' => 'Fishing Exp Boost', 'tier' => 'RARE'],
        'PET_ITEM_FISHING_SKILL_BOOST_EPIC' => ['name' => 'Fishing Exp Boost', 'tier' => 'EPIC'],
        'PET_ITEM_MINING_SKILL_BOOST_COMMON' => ['name' => 'Mining Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_MINING_SKILL_BOOST_UNCOMMON' => ['name' => 'Mining Exp Boost', 'tier' => 'UNCOMMON'],
        'PET_ITEM_MINING_SKILL_BOOST_RARE' => ['name' => 'Mining Exp Boost', 'tier' => 'RARE'],
        'PET_ITEM_MINING_SKILL_BOOST_EPIC' => ['name' => 'Mining Exp Boost', 'tier' => 'EPIC'],
        'PET_ITEM_FORAGING_SKILL_BOOST_COMMON' => ['name' => 'Foraging Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_FORAGING_SKILL_BOOST_UNCOMMON' => ['name' => 'Foraging Exp Boost', 'tier' => 'UNCOMMON'],
        'PET_ITEM_FORAGING_SKILL_BOOST_RARE' => ['name' => 'Foraging Exp Boost', 'tier' => 'RARE'],
        'PET_ITEM_FORAGING_SKILL_BOOST_EPIC' => ['name' => 'Foraging Exp Boost', 'tier' => 'EPIC'],
        'PET_ITEM_FARMING_SKILL_BOOST_COMMON' => ['name' => 'Farming Exp Boost', 'tier' => 'COMMON'],
        'PET_ITEM_FARMING_SKILL_BOOST_UNCOMMON' => ['name' => 'Farming Exp Boost', 'tier' => 'UNCOMMON'],
        'PET_ITEM_FARMING_SKILL_BOOST_RARE' => ['name' => 'Farming Exp Boost', 'tier' => 'RARE'],
        'PET_ITEM_FARMING_SKILL_BOOST_EPIC' => ['name' => 'Farming Exp Boost', 'tier' => 'EPIC'],
        'PET_ITEM_SPOOKY_CUPCAKE' => ['name' => 'Spooky Cupcake', 'tier' => 'UNCOMMON'],
        'MINOS_RELIC' => ['name' => 'Minos Relic', 'tier' => 'EPIC'],
        'DWARF_TURTLE_SHELMET' => ['name' => 'Dwarf Turtle Shelmet', 'tier' => 'RARE'],
        'PET_ITEM_QUICK_CLAW' => ['name' => 'Quick Claw', 'tier' => 'EPIC'],
        'ANTIQUE_REMEDIES' => ['name' => 'Antique Remedies', 'tier' => 'EPIC'],
        'CROCHET_TIGER_PLUSHIE' => ['name' => 'Crochet Tiger Plushie', 'tier' => 'EPIC'],
        'REAPER_GEM' => ['name' => 'Reaper Gem', 'tier' => 'LEGENDARY'],
        'PET_ITEM_FLYING_PIG' => ['name' => 'Flying Pig', 'tier' => 'UNCOMMON'],
        'GREEN_BANDANA' => ['name' => 'Green Bandana', 'tier' => 'LEGENDARY'],
    ];

    /**
     * GET /api/skycrypt/{username}
     */
    public function profile(string $username): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]{1,16}$/', $username)) {
            return response()->json(['error' => 'Invalid Minecraft username.'], 422);
        }

        $cacheKey = 'skycrypt:profile:' . strtolower($username);

        // ── Try cache ────────────────────────────────────────────────
        $cached = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return response()->json(['source' => 'cache', 'data' => $cached]);
        }

        // ── Resolve UUID via Mojang ──────────────────────────────────
        $uuid = $this->getUuidFromMojang($username);
        if ($uuid === null) {
            return response()->json(['error' => 'Player not found (Mojang lookup failed).'], 404);
        }

        // ── Fetch profiles from Hypixel API v2 ──────────────────────
        $apiKey = env('HYPIXEL_API_KEY', '');
        if (empty($apiKey)) {
            return response()->json(['error' => 'Hypixel API key not configured.'], 500);
        }

        $rawProfiles = $this->fetchHypixelProfiles($uuid, $apiKey);
        if ($rawProfiles === null) {
            return response()->json([
                'error' => 'Failed to fetch profile data from Hypixel API. Try again later.',
            ], 502);
        }

        if (empty($rawProfiles)) {
            return response()->json(['error' => 'Player has no SkyBlock profiles.'], 404);
        }

        // ── Fetch museum data for each profile ───────────────────────
        $museumDataByProfile = [];
        foreach ($rawProfiles as $profile) {
            $profileId = $profile['profile_id'] ?? null;
            if (! $profileId) continue;
            $museumDataByProfile[$profileId] = $this->fetchMuseumData($profileId, $uuid, $apiKey);
        }

        // ── Fetch player data for rank info ──────────────────────────
        $playerData = $this->fetchHypixelPlayer($uuid, $apiKey);
        $rankData   = $this->parseRank($playerData);

        // ── Transform into front-end format ──────────────────────────
        $data = $this->transformProfiles($rawProfiles, $uuid, $username, $museumDataByProfile);
        $data['rank'] = $rankData;

        // ── Sanitize: ensure all strings are valid UTF-8 for JSON ────
        $data = $this->sanitizeForJson($data);

        // ── Cache result ─────────────────────────────────────────────
        $this->cachePut($cacheKey, $data);

        return response()->json(['source' => 'api', 'data' => $data]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Hypixel API
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Call Hypixel v2/skyblock/profiles with retry logic.
     * Returns the array of profiles or null on failure.
     */
    private function fetchHypixelProfiles(string $uuid, string $apiKey): ?array
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(20)
                    ->connectTimeout(10)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'SkyblockHub/1.0'])
                    ->get('https://api.hypixel.net/v2/skyblock/profiles', [
                        'key'  => $apiKey,
                        'uuid' => $uuid,
                    ]);

                if ($response->status() === 429) {
                    $wait = max((int) $response->header('Retry-After', 3), pow(2, $attempt + 1));
                    Log::warning('Hypixel rate-limited', ['uuid' => $uuid, 'wait' => $wait, 'attempt' => $attempt + 1]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                if ($response->serverError()) {
                    $wait = pow(2, $attempt + 1);
                    Log::warning('Hypixel server error', ['status' => $response->status(), 'attempt' => $attempt + 1]);
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                if (! $response->successful()) {
                    Log::error('Hypixel unexpected status', ['status' => $response->status(), 'body' => $response->body()]);
                    return null;
                }

                $json = $response->json();
                if (($json['success'] ?? false) !== true) {
                    Log::error('Hypixel API returned success=false', ['cause' => $json['cause'] ?? 'unknown']);
                    return null;
                }

                return $json['profiles'] ?? [];

            } catch (\Exception $e) {
                $wait = pow(2, $attempt + 1);
                Log::error('Hypixel HTTP exception', ['uuid' => $uuid, 'exception' => $e->getMessage(), 'attempt' => $attempt + 1]);
                sleep($wait);
                $attempt++;
            }
        }

        Log::error('Hypixel fetch failed after retries', ['uuid' => $uuid]);
        return null;
    }

    /**
     * Fetch museum data from Hypixel API v2 for networth calculation.
     * Returns the member's museum data or null on failure.
     */
    private function fetchMuseumData(string $profileId, string $uuid, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'SkyblockHub/1.0'])
                ->get('https://api.hypixel.net/v2/skyblock/museum', [
                    'key'     => $apiKey,
                    'profile' => $profileId,
                ]);

            if (! $response->successful()) {
                Log::warning('Museum API failed', ['profile' => $profileId, 'status' => $response->status()]);
                return null;
            }

            $json = $response->json();
            if (($json['success'] ?? false) !== true) {
                return null;
            }

            return $json['members'][$uuid] ?? null;
        } catch (\Exception $e) {
            Log::warning('Museum API exception', ['profile' => $profileId, 'exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch player data from Hypixel API v2 (for rank info).
     */
    private function fetchHypixelPlayer(string $uuid, string $apiKey): ?array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'SkyblockHub/1.0'])
                ->get('https://api.hypixel.net/v2/player', [
                    'key'  => $apiKey,
                    'uuid' => $uuid,
                ]);

            if (! $response->successful()) return null;

            $json = $response->json();
            if (($json['success'] ?? false) !== true) return null;

            return $json['player'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Player API exception', ['uuid' => $uuid, 'exception' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse Hypixel rank from player data (mirrors SkyCrypt helper.js).
     * Returns array with 'prefix', 'color', and optionally 'plusColor'.
     */
    private function parseRank(?array $player): array
    {
        if (! $player) return ['prefix' => null, 'color' => '#AAAAAA'];

        $rank            = $player['rank'] ?? null;
        $monthlyRank     = $player['monthlyPackageRank'] ?? null;
        $newPackageRank  = $player['newPackageRank'] ?? null;
        $packageRank     = $player['packageRank'] ?? null;
        $rankPlusColor   = $player['rankPlusColor'] ?? null;
        $monthlyRankColor = $player['monthlyRankColor'] ?? null;

        // Special staff/content creator ranks
        if ($rank === 'ADMIN')      return ['prefix' => '[ADMIN]', 'color' => '#FF5555'];
        if ($rank === 'MODERATOR')  return ['prefix' => '[MOD]', 'color' => '#00AA00'];
        if ($rank === 'GAME_MASTER') return ['prefix' => '[GM]', 'color' => '#00AA00'];
        if ($rank === 'HELPER')     return ['prefix' => '[HELPER]', 'color' => '#5555FF'];
        if ($rank === 'YOUTUBER')   return ['prefix' => '[YOUTUBE]', 'color' => '#FF5555'];

        // MVP++
        if ($monthlyRank === 'SUPERSTAR') {
            $nameColor = self::MC_COLOR_MAP[$monthlyRankColor ?? 'GOLD'] ?? '#FFAA00';
            $plusColor  = self::MC_COLOR_MAP[$rankPlusColor ?? 'RED'] ?? '#FF5555';
            return ['prefix' => '[MVP++]', 'color' => $nameColor, 'plusColor' => $plusColor];
        }

        // MVP+
        if (($newPackageRank ?? $packageRank) === 'MVP_PLUS') {
            $plusColor = self::MC_COLOR_MAP[$rankPlusColor ?? 'RED'] ?? '#FF5555';
            return ['prefix' => '[MVP+]', 'color' => '#55FFFF', 'plusColor' => $plusColor];
        }

        // MVP
        if (in_array($newPackageRank ?? $packageRank, ['MVP', 'MVP_PLUS'])) {
            return ['prefix' => '[MVP]', 'color' => '#55FFFF'];
        }

        // VIP+
        if (($newPackageRank ?? $packageRank) === 'VIP_PLUS') {
            return ['prefix' => '[VIP+]', 'color' => '#55FF55', 'plusColor' => '#FFAA00'];
        }

        // VIP
        if (($newPackageRank ?? $packageRank) === 'VIP') {
            return ['prefix' => '[VIP]', 'color' => '#55FF55'];
        }

        // No rank
        return ['prefix' => null, 'color' => '#AAAAAA'];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Data transformation (mirrors SkyCrypt logic)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Transform raw Hypixel profiles response into the structure expected
     * by the Vue frontend:
     *
     *  {
     *    profiles: {
     *      "<profile_id>": {
     *        cute_name: "Strawberry",
     *        selected: true,
     *        game_mode: "normal",
     *        data: { skills: {…}, slayers: {…}, dungeons: {…}, networth: {…} }
     *      }
     *    }
     *  }
     */
    private function transformProfiles(array $rawProfiles, string $uuid, string $username, array $museumDataByProfile = []): array
    {
        $profiles = [];

        foreach ($rawProfiles as $profile) {
            $profileId = $profile['profile_id'] ?? null;
            if (! $profileId) continue;

            $member = $profile['members'][$uuid] ?? null;
            if (! $member) continue;

            $skills = $this->parseSkills($member);

            // Average skill level (exclude runecrafting and social, like SkyCrypt)
            $countable = array_filter($skills, fn($v, $k) => !in_array($k, ['runecrafting', 'social']), ARRAY_FILTER_USE_BOTH);
            $avgSkillLevel = count($countable) > 0
                ? round(array_sum(array_column($countable, 'level')) / count($countable), 2)
                : 0;

            // ── Calculate networth via SkyHelper-Networth ────────────
            $museumData   = $museumDataByProfile[$profileId] ?? null;
            $bankBalance  = $profile['banking']['balance'] ?? 0;
            $networthData = $this->calculateNetworth($member, $museumData, $bankBalance);

            // Extract price maps for injecting into parsed items
            $pricesByUuid = $networthData['itemPricesByUuid'] ?? [];
            $pricesById   = $networthData['itemPricesById'] ?? [];

            // Remove raw price maps from the networth response sent to frontend
            unset($networthData['itemPricesByUuid'], $networthData['itemPricesById']);

            // Parse all inventory sections first
            $armor      = $this->parseArmor($member);
            $equipment  = $this->parseEquipment($member);
            $wardrobe   = $this->parseWardrobe($member);
            $weapons    = $this->parseWeapons($member);
            $accessories= $this->parseAccessories($member);
            $inventory  = $this->parsePlayerInventory($member);
            $enderchest = $this->parseEnderChest($member);
            $personalVault = $this->parsePersonalVault($member);
            $fishingBag = $this->parseBagContents($member, 'fishing_bag');
            $potionBag  = $this->parseBagContents($member, 'potion_bag');
            $quiver     = $this->parseBagContents($member, 'quiver');
            $storage    = $this->parseBackpackStorage($member);

            // Inject item values into all flat item arrays
            $armor      = $this->injectItemValues($armor, $pricesByUuid, $pricesById);
            $equipment  = $this->injectItemValues($equipment, $pricesByUuid, $pricesById);
            $weapons    = $this->injectItemValues($weapons, $pricesByUuid, $pricesById);
            $accessories= $this->injectItemValues($accessories, $pricesByUuid, $pricesById);
            $inventory  = $this->injectItemValues($inventory, $pricesByUuid, $pricesById);
            $enderchest = $this->injectItemValuesNested($enderchest, $pricesByUuid, $pricesById);
            $personalVault = $this->injectItemValues($personalVault, $pricesByUuid, $pricesById);
            $fishingBag = $this->injectItemValues($fishingBag, $pricesByUuid, $pricesById);
            $potionBag  = $this->injectItemValues($potionBag, $pricesByUuid, $pricesById);
            $quiver     = $this->injectItemValues($quiver, $pricesByUuid, $pricesById);
            $wardrobe   = $this->injectItemValuesWardrobe($wardrobe, $pricesByUuid, $pricesById);
            $storage    = $this->injectItemValuesStorage($storage, $pricesByUuid, $pricesById);

            $profiles[$profileId] = [
                'cute_name' => $profile['cute_name'] ?? 'Unknown',
                'selected'  => $profile['selected'] ?? false,
                'game_mode' => $profile['game_mode'] ?? 'normal',
                'data'      => [
                    'skyblock_level'      => $this->parseSkyblockLevel($member),
                    'fairy_souls'         => $member['fairy_soul']['total_collected'] ?? $member['fairy_exchanges'] ?? 0,
                    'first_join'          => $member['profile']['first_join'] ?? null,
                    'average_skill_level' => $avgSkillLevel,
                    'skills'     => $skills,
                    'slayers'    => $this->parseSlayers($member),
                    'collections'=> $this->parseCollections($member),
                    'dungeons'   => $this->parseDungeons($member),
                    'networth'   => $networthData,
                    'pets'       => $this->parsePets($member),
                    'armor'      => $armor,
                    'equipment'  => $equipment,
                    'wardrobe'   => $wardrobe,
                    'weapons'    => $weapons,
                    'accessories'=> $accessories,
                    'talisman_bag'   => $this->parseBagContents($member, 'talisman_bag'),
                    'inventory'  => $inventory,
                    'enderchest' => $enderchest,
                    'personal_vault' => $personalVault,
                    'fishing_bag'    => $fishingBag,
                    'potion_bag'     => $potionBag,
                    'quiver'         => $quiver,
                    'candy_bag'      => $this->parseCandyBag($member, $profile),
                    'storage'        => $storage,
                    'museum'         => $this->parseMuseum($member, $profile),
                    'rift_inventory' => $this->parseRiftInventory($member),
                    'rift_enderchest'=> $this->parseRiftEnderchest($member),
                    'accessory_bag_storage' => $this->parseAccessoryBagStorage($member),
                    'wardrobe_slot' => $member['inventory']['wardrobe_equipped_slot'] ?? null,
                    'inv_disabled'  => empty($member['inventory']['inv_contents']['data'] ?? null),
                    'player_stats'  => $this->calculatePlayerStats($member, $skills, $armor, $equipment, $accessories),
                ],
            ];
        }

        return [
            'uuid'     => $uuid,
            'username' => $username,
            'profiles' => $profiles,
        ];
    }

    // ─── Skills ──────────────────────────────────────────────────────

    private function parseSkills(array $member): array
    {
        $skills = [];

        $experience = $member['player_data']['experience'] ?? [];

        $skillNames = [
            'farming', 'mining', 'combat', 'foraging', 'fishing',
            'enchanting', 'alchemy', 'carpentry', 'taming', 'runecrafting', 'social', 'hunting',
        ];

        foreach ($skillNames as $name) {
            $key = 'SKILL_' . strtoupper($name);
            $xp  = $experience[$key] ?? 0;

            $maxLevel = in_array($name, ['runecrafting', 'social', 'hunting']) ? 25 : 60;
            $detail   = $this->xpToLevelDetailed($xp, self::SKILL_XP_TABLE, $maxLevel);

            $skills[$name] = [
                'level'     => $detail['level'],
                'maxLevel'  => $maxLevel,
                'xp'        => $xp,
                'xpCurrent' => $detail['xpCurrent'],
                'xpForNext' => $detail['xpForNext'],
                'progress'  => $detail['progress'],
            ];
        }

        return $skills;
    }

    // ─── Slayers ─────────────────────────────────────────────────────

    private function parseSlayers(array $member): array
    {
        $output = ['slayers' => [], 'total_slayer_xp' => 0, 'total_coins_spent' => 0];

        $slayerBosses = $member['slayer']['slayer_bosses'] ?? $member['slayer_bosses'] ?? [];

        foreach ($slayerBosses as $name => $data) {
            if (! is_array($data)) continue;

            $xp = $data['xp'] ?? 0;

            $table    = self::SLAYER_XP_TABLE[$name] ?? self::SLAYER_XP_TABLE['zombie'];
            $maxLevel = count($table) - 1;
            $level    = 0;

            for ($i = $maxLevel; $i >= 0; $i--) {
                if ($xp >= $table[$i]) {
                    $level = $i;
                    break;
                }
            }

            // XP progress towards next level
            $progress  = 0;
            $xpForNext = 0;
            $xpCurrent = 0;
            if ($level < $maxLevel) {
                $nextThreshold = $table[$level + 1];
                $currentThreshold = $table[$level];
                $xpCurrent = $xp - $currentThreshold;
                $xpForNext = $nextThreshold - $currentThreshold;
                $progress  = $xpForNext > 0 ? $xpCurrent / $xpForNext : 0;
            } else {
                $progress = 1;
                $xpCurrent = $xp - $table[$maxLevel];
            }

            // Parse kills per tier
            $kills = [];
            $coinsSpent = 0;
            $totalKills = 0;
            foreach ($data as $property => $value) {
                if (str_starts_with($property, 'boss_kills_tier_')) {
                    $tier = ((int) substr($property, strrpos($property, '_') + 1)) + 1;
                    $kills[$tier] = $value;
                    $totalKills += $value;
                    $coinsSpent += $value * (self::SLAYER_COST[$tier] ?? 0);
                }
            }

            $output['slayers'][$name] = [
                'name'        => self::SLAYER_INFO[$name] ?? ucfirst($name),
                'level'       => [
                    'currentLevel' => $level,
                    'maxLevel'     => $maxLevel,
                    'xp'           => $xp,
                    'xpCurrent'    => $xpCurrent,
                    'xpForNext'    => $xpForNext,
                    'progress'     => $progress,
                ],
                'xp'          => $xp,
                'kills'       => $kills,
                'total_kills' => $totalKills,
                'coins_spent' => $coinsSpent,
            ];
        }

        $output['total_slayer_xp']   = array_sum(array_column($output['slayers'], 'xp'));
        $output['total_coins_spent'] = array_sum(array_column(array_values($output['slayers']), 'coins_spent'));

        return $output;
    }

    // ─── Collections ─────────────────────────────────────────────────

    /**
     * Fetch collection definitions from Hypixel resources API (cached 24h).
     */
    private function getCollectionDefinitions(): ?array
    {
        return Cache::remember('hypixel:collection_definitions', 86400, function () {
            try {
                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->acceptJson()
                    ->get('https://api.hypixel.net/v2/resources/skyblock/collections');

                if (! $response->successful()) return null;

                $json = $response->json();
                if (($json['success'] ?? false) !== true) return null;

                return $json['collections'] ?? null;
            } catch (\Exception $e) {
                Log::warning('Collection definitions fetch failed', ['exception' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Parse player collections from member data, using
     * the Hypixel resources API for tier definitions.
     */
    private function parseCollections(array $member): array
    {
        $definitions = $this->getCollectionDefinitions();
        if ($definitions === null) {
            return ['categories' => [], 'totalCollections' => 0, 'maxedCollections' => 0];
        }

        $playerCollections = $member['collection'] ?? [];

        $categories = [];
        $totalCollections = 0;
        $maxedCollections = 0;

        foreach ($definitions as $categoryId => $category) {
            $categoryName = $category['name'] ?? ucfirst(strtolower($categoryId));
            $items = $category['items'] ?? [];

            $categoryCollections = [];
            $categoryTotal = 0;
            $categoryMaxed = 0;

            foreach ($items as $itemId => $itemDef) {
                $name     = $itemDef['name'] ?? $itemId;
                $maxTier  = $itemDef['maxTiers'] ?? 0;
                $tiers    = $itemDef['tiers'] ?? [];
                $amount   = $playerCollections[$itemId] ?? 0;

                // Calculate current tier
                $currentTier = 0;
                foreach ($tiers as $tierDef) {
                    if ($amount >= ($tierDef['amountRequired'] ?? PHP_INT_MAX)) {
                        $currentTier = $tierDef['tier'] ?? 0;
                    }
                }

                // Next tier info
                $nextTierAmount = null;
                if ($currentTier < $maxTier && isset($tiers[$currentTier])) {
                    $nextTierAmount = $tiers[$currentTier]['amountRequired'] ?? null;
                }

                $progress = 0;
                if ($nextTierAmount !== null && $nextTierAmount > 0) {
                    $prevAmount = $currentTier > 0 && isset($tiers[$currentTier - 1])
                        ? ($tiers[$currentTier - 1]['amountRequired'] ?? 0)
                        : 0;
                    $diff = $nextTierAmount - $prevAmount;
                    $progress = $diff > 0 ? min(1, max(0, ($amount - $prevAmount) / $diff)) : 0;
                } elseif ($currentTier >= $maxTier && $maxTier > 0) {
                    $progress = 1;
                }

                $isMaxed = $currentTier >= $maxTier && $maxTier > 0;

                $categoryCollections[] = [
                    'id'        => $itemId,
                    'name'      => $name,
                    'amount'    => $amount,
                    'tier'      => $currentTier,
                    'maxTier'   => $maxTier,
                    'maxed'     => $isMaxed,
                    'unlocked'  => $amount > 0,
                    'progress'  => $progress,
                    'nextTierAmount' => $nextTierAmount,
                ];

                $categoryTotal++;
                $totalCollections++;
                if ($isMaxed) {
                    $categoryMaxed++;
                    $maxedCollections++;
                }
            }

            // Sort: maxed first, then by tier desc, then by name
            usort($categoryCollections, function ($a, $b) {
                if ($a['maxed'] !== $b['maxed']) return $b['maxed'] <=> $a['maxed'];
                if ($a['tier'] !== $b['tier']) return $b['tier'] <=> $a['tier'];
                return strcmp($a['name'], $b['name']);
            });

            $categories[$categoryId] = [
                'name'        => $categoryName,
                'collections' => $categoryCollections,
                'totalTiers'  => $categoryTotal,
                'maxedTiers'  => $categoryMaxed,
            ];
        }

        return [
            'categories'       => $categories,
            'totalCollections' => $totalCollections,
            'maxedCollections' => $maxedCollections,
        ];
    }

    // ─── Dungeons ────────────────────────────────────────────────────

    private const FLOOR_NAMES = [
        'catacombs_0' => 'Entrance',
        'catacombs_1' => 'Floor 1', 'catacombs_2' => 'Floor 2', 'catacombs_3' => 'Floor 3',
        'catacombs_4' => 'Floor 4', 'catacombs_5' => 'Floor 5', 'catacombs_6' => 'Floor 6',
        'catacombs_7' => 'Floor 7',
        'master_catacombs_1' => 'Floor 1', 'master_catacombs_2' => 'Floor 2',
        'master_catacombs_3' => 'Floor 3', 'master_catacombs_4' => 'Floor 4',
        'master_catacombs_5' => 'Floor 5', 'master_catacombs_6' => 'Floor 6',
        'master_catacombs_7' => 'Floor 7',
    ];

    private const DUNGEON_STAT_KEYS = [
        'times_played', 'tier_completions', 'milestone_completions',
        'mobs_killed', 'best_score', 'watcher_kills',
        'most_mobs_killed', 'fastest_time', 'fastest_time_s', 'fastest_time_s_plus',
        'most_healing',
    ];

    private function parseDungeons(array $member): array
    {
        $dungeons = $member['dungeons'] ?? null;
        if (! $dungeons || empty($dungeons['dungeon_types'])) {
            return [];
        }

        $catacombs = $dungeons['dungeon_types']['catacombs'] ?? [];
        $xp        = $catacombs['experience'] ?? 0;
        $detail    = $this->xpToLevelDetailed($xp, self::DUNGEON_XP_TABLE, 50);

        // Class levels
        $classes     = [];
        $classNames  = ['healer', 'mage', 'berserk', 'archer', 'tank'];
        $playerClass = $dungeons['player_classes'] ?? [];

        foreach ($classNames as $cn) {
            $classXp     = $playerClass[$cn]['experience'] ?? 0;
            $classDetail = $this->xpToLevelDetailed($classXp, self::DUNGEON_XP_TABLE, 50);
            $classes[$cn] = [
                'level'    => $classDetail['level'],
                'xp'       => $classXp,
                'xpCurrent'=> $classDetail['xpCurrent'],
                'xpForNext'=> $classDetail['xpForNext'],
                'progress' => $classDetail['progress'],
                'maxLevel' => 50,
            ];
        }

        // Class average
        $classLevels = array_column($classes, 'level');
        $classAvg = count($classLevels) > 0 ? array_sum($classLevels) / count($classLevels) : 0;

        // Parse floors (normal catacombs)
        $normalFloors = $this->parseDungeonFloors($catacombs, 'catacombs');

        // Parse master catacombs
        $masterCata   = $dungeons['dungeon_types']['master_catacombs'] ?? [];
        $masterFloors = $this->parseDungeonFloors($masterCata, 'master_catacombs');

        // Highest floor beaten
        $highestNormal = $catacombs['highest_tier_completed'] ?? null;
        $highestMaster = $masterCata['highest_tier_completed'] ?? null;

        // Total completions for S/R calculation
        $totalCompletions = 0;
        foreach ($normalFloors as $f) { $totalCompletions += $f['stats']['tier_completions'] ?? 0; }
        foreach ($masterFloors as $f) { $totalCompletions += $f['stats']['tier_completions'] ?? 0; }

        $secretsFound = $dungeons['secrets'] ?? 0;
        $secretsPerRun = $totalCompletions > 0 ? round($secretsFound / $totalCompletions, 2) : 0;

        return [
            'catacombs' => [
                'level' => [
                    'level'     => $detail['level'],
                    'xp'        => $xp,
                    'xpCurrent' => $detail['xpCurrent'],
                    'xpForNext' => $detail['xpForNext'],
                    'progress'  => $detail['progress'],
                    'maxLevel'  => 50,
                ],
            ],
            'secrets_found'     => $secretsFound,
            'secrets_per_run'   => $secretsPerRun,
            'classes'           => $classes,
            'class_average'     => round($classAvg, 2),
            'selected_class'    => $dungeons['selected_dungeon_class'] ?? null,
            'highest_floor'     => $highestNormal,
            'highest_master'    => $highestMaster,
            'floors'            => $normalFloors,
            'master_floors'     => $masterFloors,
        ];
    }

    private function parseDungeonFloors(array $dungeonType, string $prefix): array
    {
        $floors = [];

        // Collect all floor indices
        $floorIndices = [];
        foreach (self::DUNGEON_STAT_KEYS as $statKey) {
            if (isset($dungeonType[$statKey]) && is_array($dungeonType[$statKey])) {
                foreach (array_keys($dungeonType[$statKey]) as $idx) {
                    if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
                }
            }
        }
        // Also check best_runs
        if (isset($dungeonType['best_runs']) && is_array($dungeonType['best_runs'])) {
            foreach (array_keys($dungeonType['best_runs']) as $idx) {
                if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
            }
        }
        // Also check most_damage_* keys
        foreach ($dungeonType as $key => $val) {
            if (str_starts_with($key, 'most_damage_') && is_array($val)) {
                foreach (array_keys($val) as $idx) {
                    if (is_numeric($idx)) $floorIndices[(int)$idx] = true;
                }
            }
        }

        ksort($floorIndices);

        foreach (array_keys($floorIndices) as $floorIdx) {
            $floorId = "{$prefix}_{$floorIdx}";
            $name    = self::FLOOR_NAMES[$floorId] ?? "Floor $floorIdx";

            // Gather stats
            $stats = [];
            foreach (self::DUNGEON_STAT_KEYS as $statKey) {
                $val = $dungeonType[$statKey][$floorIdx] ?? null;
                if ($val !== null) {
                    $stats[$statKey] = $val;
                }
            }

            // Most damage (find best across all classes)
            $mostDamage = null;
            foreach ($dungeonType as $key => $val) {
                if (str_starts_with($key, 'most_damage_') && is_array($val) && isset($val[$floorIdx])) {
                    $className = str_replace('most_damage_', '', $key);
                    if ($mostDamage === null || $val[$floorIdx] > $mostDamage['value']) {
                        $mostDamage = ['class' => $className, 'value' => $val[$floorIdx]];
                    }
                }
            }

            // Best run (last entry in best_runs array — the overall best)
            $bestRun = null;
            if (isset($dungeonType['best_runs'][$floorIdx]) && is_array($dungeonType['best_runs'][$floorIdx])) {
                $runs = $dungeonType['best_runs'][$floorIdx];
                $run = end($runs);
                if ($run) {
                    $bestRun = [
                        'timestamp'       => $run['timestamp'] ?? null,
                        'score_exploration'=> $run['score_exploration'] ?? 0,
                        'score_speed'      => $run['score_speed'] ?? 0,
                        'score_skill'      => $run['score_skill'] ?? 0,
                        'score_bonus'      => $run['score_bonus'] ?? 0,
                        'dungeon_class'    => $run['dungeon_class'] ?? null,
                        'elapsed_time'     => $run['elapsed_time'] ?? null,
                        'damage_dealt'     => $run['damage_dealt'] ?? 0,
                        'deaths'           => $run['deaths'] ?? 0,
                        'mobs_killed'      => $run['mobs_killed'] ?? 0,
                        'secrets_found'    => $run['secrets_found'] ?? 0,
                        'damage_mitigated' => $run['damage_mitigated'] ?? 0,
                    ];
                    // Calculate grade
                    $totalScore = ($bestRun['score_exploration'] + $bestRun['score_speed'] + $bestRun['score_skill'] + $bestRun['score_bonus']);
                    $bestRun['grade'] = $this->calcDungeonGrade($totalScore);
                }
            }

            $floors[] = [
                'index'       => $floorIdx,
                'name'        => $name,
                'stats'       => $stats,
                'most_damage' => $mostDamage,
                'best_run'    => $bestRun,
            ];
        }

        return $floors;
    }

    private function calcDungeonGrade(int $score): string
    {
        if ($score >= 300) return 'S+';
        if ($score >= 270) return 'S';
        if ($score >= 240) return 'A';
        if ($score >= 175) return 'B';
        if ($score >= 110) return 'C';
        if ($score >= 60)  return 'D';
        return 'F';
    }

    // ─── Networth (via SkyHelper-Networth Node.js) ─────────────────

    /**
     * Calculate networth by invoking the SkyHelper-Networth Node.js script.
     * Falls back to basic purse + bank if the script fails.
     */
    private function calculateNetworth(array $member, ?array $museumData, float $bankBalance): array
    {
        $purse = $member['currencies']['coin_purse'] ?? 0;

        // Prepare input for Node.js script
        $input = json_encode([
            'profileData' => $member,
            'museumData'  => $museumData,
            'bankBalance' => $bankBalance,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($input === false) {
            Log::warning('Networth: failed to encode input JSON');
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        $scriptPath = base_path('scripts/networth.cjs');
        $nodePath   = 'node';

        // Call the Node.js script via subprocess
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = @proc_open(
            [$nodePath, $scriptPath],
            $descriptors,
            $pipes,
            base_path()
        );

        if (! is_resource($process)) {
            Log::warning('Networth: failed to start Node.js process');
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        // Write input and close stdin
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        // Read output (with timeout)
        stream_set_timeout($pipes[1], 30);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::warning('Networth: Node.js script failed', [
                'exitCode' => $exitCode,
                'stderr'   => mb_substr($stderr, 0, 500),
            ]);
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        $result = @json_decode($stdout, true);
        if (! $result || ! isset($result['networth'])) {
            Log::warning('Networth: invalid JSON output from Node.js', [
                'stdout' => mb_substr($stdout, 0, 500),
            ]);
            return $this->fallbackNetworth($purse, $bankBalance);
        }

        return [
            'networth'             => $result['networth'] ?? 0,
            'unsoulboundNetworth'  => $result['unsoulboundNetworth'] ?? 0,
            'purse'                => $result['purse'] ?? $purse,
            'bank'                 => $result['bank'] ?? $bankBalance,
            'personalBank'         => $result['personalBank'] ?? 0,
            'noInventory'          => $result['noInventory'] ?? false,
            'categories'           => $result['categories'] ?? [],
            'itemPricesByUuid'     => $result['itemPricesByUuid'] ?? [],
            'itemPricesById'       => $result['itemPricesById'] ?? [],
        ];
    }

    /**
     * Fallback networth when Node.js calculation fails.
     */
    private function fallbackNetworth(float $purse, float $bank): array
    {
        return [
            'networth'             => $purse + $bank,
            'unsoulboundNetworth'  => $purse + $bank,
            'purse'                => $purse,
            'bank'                 => $bank,
            'personalBank'         => 0,
            'noInventory'          => false,
            'categories'           => [],
            'itemPricesByUuid'     => [],
            'itemPricesById'       => [],
        ];
    }

    // ─── SkyBlock Level ────────────────────────────────────────────────

    private function parseSkyblockLevel(array $member): array
    {
        $xp = $member['leveling']['experience'] ?? 0;
        $level     = (int) floor($xp / 100);
        $xpCurrent = fmod($xp, 100);
        $xpForNext = 100;
        $progress  = $xpCurrent / $xpForNext;

        return [
            'level'     => $level,
            'xpCurrent' => round($xpCurrent, 2),
            'xpForNext' => $xpForNext,
            'progress'  => round($progress, 4),
            'totalXp'   => $xp,
        ];
    }

    // ─── Inventory / Items ─────────────────────────────────────────────

    /**
     * Inject item values from SkyHelper-Networth into parsed items.
     * Matches by UUID first (unique per item), then falls back to skyblock_id.
     * Also appends "Item Value" lore lines to item tooltips like SkyCrypt.
     */
    private function injectItemValues(array $items, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($items as &$item) {
            if ($item === null) continue;

            $uuid       = $item['uuid'] ?? null;
            $skyblockId = $item['skyblock_id'] ?? null;
            $priceEntry = null;

            // Try UUID match first (most reliable)
            if ($uuid !== null && isset($pricesByUuid[$uuid])) {
                $priceEntry = $pricesByUuid[$uuid];
                unset($pricesByUuid[$uuid]); // consumed
            }
            // Fallback: match by skyblock_id
            elseif ($skyblockId !== null && isset($pricesById[$skyblockId]) && !empty($pricesById[$skyblockId])) {
                $priceEntry = array_shift($pricesById[$skyblockId]);
            }

            if ($priceEntry !== null) {
                $price     = $priceEntry['price'] ?? 0;
                $soulbound = $priceEntry['soulbound'] ?? false;

                $item['item_value']     = $price;
                $item['item_soulbound'] = $soulbound;

                // Append Item Value to lore_html (like SkyCrypt)
                if ($price > 0 && isset($item['lore_html']) && is_array($item['lore_html'])) {
                    $item['lore_html'][] = ''; // empty separator line
                    $formattedFull  = number_format($price);
                    $formattedShort = ItemParser::formatNumberPublic($price);

                    if ($soulbound) {
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§7Item Value: §6{$formattedFull} Coins §7(§6{$formattedShort}§7)"
                        );
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§8(Soulbound)"
                        );
                    } else {
                        $item['lore_html'][] = ItemParser::colorCodeToHtml(
                            "§7Item Value: §6{$formattedFull} Coins §7(§6{$formattedShort}§7)"
                        );
                    }
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * Inject item values into enderchest pages (nested structure with 'items' arrays).
     */
    private function injectItemValuesNested(array $pages, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($pages as &$page) {
            if (isset($page['items']) && is_array($page['items'])) {
                $page['items'] = $this->injectItemValues($page['items'], $pricesByUuid, $pricesById);
            }
        }
        unset($page);
        return $pages;
    }

    /**
     * Inject item values into wardrobe sets (array of sets, each set is array of 4 items).
     */
    private function injectItemValuesWardrobe(array $sets, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($sets as &$set) {
            if (is_array($set)) {
                $set = $this->injectItemValues($set, $pricesByUuid, $pricesById);
            }
        }
        unset($set);
        return $sets;
    }

    /**
     * Inject item values into backpack storage (array of slots with 'items' and 'icon').
     */
    private function injectItemValuesStorage(array $storage, array &$pricesByUuid, array &$pricesById): array
    {
        foreach ($storage as &$slot) {
            if (isset($slot['items']) && is_array($slot['items'])) {
                $slot['items'] = $this->injectItemValues($slot['items'], $pricesByUuid, $pricesById);
            }
            if (isset($slot['icon']) && is_array($slot['icon'])) {
                $one = [$slot['icon']];
                $one = $this->injectItemValues($one, $pricesByUuid, $pricesById);
                $slot['icon'] = $one[0];
            }
        }
        unset($slot);
        return $storage;
    }

    private function parseArmor(array $member): array
    {
        $data = $member['inventory']['inv_armor']['data'] ?? null;
        if (! $data) return [];

        $items = ItemParser::parseInventoryKeepSlots($data);
        // Minecraft stores armor boots-first, reverse to helm→boots
        return array_values(array_reverse($items));
    }

    private function parseEquipment(array $member): array
    {
        $data = $member['inventory']['equipment_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    private function parseWardrobe(array $member): array
    {
        $data = $member['inventory']['wardrobe_contents']['data'] ?? null;
        if (! $data) return [];

        $allItems = ItemParser::parseInventoryKeepSlots($data);
        $total    = count($allItems);
        if ($total === 0) return [];

        // Wardrobe layout: each page has 9 columns × 4 rows.
        // Row 0 (slots 0-8) = helmets, Row 1 (9-17) = chests,
        // Row 2 (18-26) = legs, Row 3 (27-35) = boots.
        // Page 2 starts at slot 36, etc.
        $slotsPerPage = 36;
        $cols         = 9;
        $sets         = [];

        $pages = (int) ceil($total / $slotsPerPage);

        for ($page = 0; $page < $pages; $page++) {
            $base = $page * $slotsPerPage;
            for ($col = 0; $col < $cols; $col++) {
                $set = [];
                for ($row = 0; $row < 4; $row++) {
                    $idx   = $base + ($row * $cols) + $col;
                    $set[] = $allItems[$idx] ?? null;
                }
                // Only include sets that have at least one item
                if (array_filter($set, fn($i) => $i !== null)) {
                    $sets[] = $set;
                }
            }
        }

        return $sets;
    }

    private function parseWeapons(array $member): array
    {
        $data = $member['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        $items   = ItemParser::parseInventory($data);
        $weapons = array_filter($items, fn($item) => self::isWeapon($item));

        // Sort by rarity descending
        usort($weapons, fn($a, $b) =>
            ItemParser::rarityOrder($b['rarity']) <=> ItemParser::rarityOrder($a['rarity'])
        );

        return array_values($weapons);
    }

    private function parseAccessories(array $member): array
    {
        $data = $member['inventory']['bag_contents']['talisman_bag']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    private function parsePlayerInventory(array $member): array
    {
        $data = $member['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    private function parseAccessoryBagStorage(array $member): array
    {
        $abs = $member['accessory_bag_storage'] ?? [];
        return [
            'selected_power'      => $abs['selected_power'] ?? null,
            'highest_magical_power'=> $abs['highest_magical_power'] ?? null,
            'tuning'              => $abs['tuning'] ?? null,
        ];
    }

    // ─── Ender Chest ──────────────────────────────────────────────────

    private function parseEnderChest(array $member): array
    {
        $data = $member['inventory']['ender_chest_contents']['data'] ?? null;
        if (! $data) return [];

        $allSlots = ItemParser::parseInventoryKeepSlots($data);
        if (empty($allSlots)) return [];

        // Split into pages of 45 slots (5 rows × 9 cols, like a double chest)
        $slotsPerPage = 45;
        $pages = array_chunk($allSlots, $slotsPerPage);
        $result = [];

        foreach ($pages as $i => $pageSlots) {
            $result[] = [
                'page'  => $i,
                'items' => $pageSlots,
                'count' => count(array_filter($pageSlots, fn($s) => $s !== null)),
            ];
        }

        return $result;
    }

    // ─── Personal Vault ───────────────────────────────────────────────

    private function parsePersonalVault(array $member): array
    {
        $data = $member['inventory']['personal_vault_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Bag Contents (fishing bag, potion bag, quiver) ───────────────

    private function parseBagContents(array $member, string $bagName): array
    {
        $data = $member['inventory']['bag_contents'][$bagName]['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Candy Bag (shared inventory) ─────────────────────────────────

    private function parseCandyBag(array $member, array $profile): array
    {
        $data = $profile['shared_inventory']['candy_inventory_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventory($data);
    }

    // ─── Backpack Storage (like SkyCrypt's storage) ───────────────────

    private function parseBackpackStorage(array $member): array
    {
        $backpackContents = $member['inventory']['backpack_contents'] ?? null;
        $backpackIcons    = $member['inventory']['backpack_icons'] ?? null;

        if (! $backpackContents || ! is_array($backpackContents)) {
            return [];
        }

        $storageSize = max(18, count($backpackContents));
        $storage     = [];

        for ($slot = 0; $slot < $storageSize; $slot++) {
            $slotKey = (string) $slot;

            if (! isset($backpackContents[$slotKey])) {
                continue;
            }

            $icon  = null;
            $items = [];

            // Parse the backpack icon (the backpack item itself)
            if (isset($backpackIcons[$slotKey]['data'])) {
                $iconItems = ItemParser::parseInventory($backpackIcons[$slotKey]['data']);
                $icon = $iconItems[0] ?? null;
            }

            // Parse the backpack contents (keep empty slots for MC grid)
            if (isset($backpackContents[$slotKey]['data'])) {
                $items = ItemParser::parseInventoryKeepSlots($backpackContents[$slotKey]['data']);
            }

            $storage[] = [
                'slot'  => $slot,
                'icon'  => $icon,
                'items' => $items,
                'count' => count(array_filter($items, fn($i) => $i !== null)),
            ];
        }

        return $storage;
    }

    // ─── Museum ───────────────────────────────────────────────────────

    private function parseMuseum(array $member, array $profile): array
    {
        $museum = $profile['museum'] ?? $member['museum'] ?? null;
        if (! $museum) return [];

        $result = [
            'value'     => $museum['value'] ?? 0,
            'appraisal' => $museum['appraisal'] ?? false,
            'items'     => [],
            'special'   => [],
        ];

        // Parse donated items
        if (isset($museum['items']) && is_array($museum['items'])) {
            foreach ($museum['items'] as $id => $data) {
                $item = [
                    'id'           => $id,
                    'donated_time' => $data['donated_time'] ?? null,
                    'borrowing'    => $data['borrowing'] ?? false,
                ];

                if (isset($data['items']['data'])) {
                    $parsed = ItemParser::parseInventory($data['items']['data']);
                    $item['data'] = $parsed;
                }

                $result['items'][] = $item;
            }
        }

        // Parse special items
        if (isset($museum['special']) && is_array($museum['special'])) {
            foreach ($museum['special'] as $id => $data) {
                $item = [
                    'id'           => $id,
                    'donated_time' => $data['donated_time'] ?? null,
                    'borrowing'    => $data['borrowing'] ?? false,
                ];

                if (isset($data['items']['data'])) {
                    $parsed = ItemParser::parseInventory($data['items']['data']);
                    $item['data'] = $parsed;
                }

                $result['special'][] = $item;
            }
        }

        return $result;
    }

    // ─── Rift Inventory ───────────────────────────────────────────────

    private function parseRiftInventory(array $member): array
    {
        $data = $member['rift']['inventory']['inv_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    // ─── Rift Ender Chest ─────────────────────────────────────────────

    private function parseRiftEnderchest(array $member): array
    {
        $data = $member['rift']['inventory']['ender_chest_contents']['data'] ?? null;
        if (! $data) return [];

        return ItemParser::parseInventoryKeepSlots($data);
    }

    private static function isWeapon(array $item): bool
    {
        $cat = $item['category'] ?? null;
        if ($cat && in_array($cat, ['SWORD', 'BOW', 'WAND', 'AXE', 'LONGSWORD', 'FISHING WEAPON', 'DRILL', 'GAUNTLET'])) {
            return true;
        }

        // Fallback: check SkyBlock ID for weapon keywords
        $id = strtolower($item['skyblock_id'] ?? '');
        foreach (['sword', 'bow', 'katana', 'blade', 'scythe', 'staff', 'wand', 'aurora'] as $kw) {
            if (str_contains($id, $kw)) return true;
        }

        return false;
    }

    // ─── Pets ─────────────────────────────────────────────────────────

    /**
     * Calculate pet level from XP, rarity, and max level (mirrors SkyCrypt getPetLevel).
     */
    private function getPetLevel(float $xp, string $rarity, int $maxLevel = 100): array
    {
        $offset    = self::PET_RARITY_OFFSET[strtoupper($rarity)] ?? 0;
        $levels    = array_slice(self::PET_LEVELS, $offset, $maxLevel - 1);
        $xpTotal   = 0;
        $level     = 1;

        foreach ($levels as $levelXp) {
            $xpTotal += $levelXp;
            if ($xpTotal > $xp) {
                $xpTotal -= $levelXp;
                break;
            }
            $level++;
        }

        if ($level > $maxLevel) $level = $maxLevel;

        $xpCurrent = $xp - $xpTotal;
        $xpForNext = ($level < $maxLevel && isset($levels[$level - 1]))
            ? $levels[$level - 1]
            : 0;
        $progress  = $xpForNext > 0 ? min($xpCurrent / $xpForNext, 1) : 1;

        return [
            'level'     => $level,
            'xpCurrent' => round($xpCurrent, 2),
            'xpForNext' => round($xpForNext, 2),
            'progress'  => round($progress, 4),
        ];
    }

    /** Pet score → Magic Find mapping (from SkyCrypt PET_REWARDS). */
    private const PET_REWARDS = [
        0   => 0,   10  => 1,  25  => 2,  50  => 3,
        75  => 4,   100 => 5,  130 => 6,  175 => 7,
        225 => 8,   275 => 9,  325 => 10, 375 => 11,
        450 => 12,  500 => 13,
    ];

    /** Point value per rarity for pet score (from SkyCrypt PET_VALUE). */
    private const PET_VALUE = [
        'common' => 1, 'uncommon' => 2, 'rare' => 3,
        'epic' => 4, 'legendary' => 5, 'mythic' => 6,
    ];

    /** Max tier per pet type for missing-pets logic (from SkyCrypt PET_DATA). */
    private const PET_MAX_TIER = [
        'ARMADILLO' => 'LEGENDARY', 'BAT' => 'MYTHIC', 'BEE' => 'LEGENDARY',
        'BLACK_CAT' => 'MYTHIC', 'BLAZE' => 'LEGENDARY', 'BLUE_WHALE' => 'LEGENDARY',
        'BABY_YETI' => 'LEGENDARY', 'BAL' => 'LEGENDARY', 'CHICKEN' => 'LEGENDARY',
        'DOLPHIN' => 'LEGENDARY', 'DROPLET_WISP' => 'UNCOMMON', 'EERIE' => 'COMMON',
        'ELEPHANT' => 'LEGENDARY', 'ENDER_DRAGON' => 'LEGENDARY', 'ENDERMAN' => 'MYTHIC',
        'ENDERMITE' => 'MYTHIC', 'FLYING_FISH' => 'MYTHIC', 'FROST_WISP' => 'RARE',
        'GHOUL' => 'LEGENDARY', 'GIRAFFE' => 'LEGENDARY', 'GLACIAL_WISP' => 'EPIC',
        'GLACITE_GOLEM' => 'LEGENDARY', 'GOBLIN' => 'LEGENDARY', 'GOLDEN_DRAGON' => 'LEGENDARY',
        'GOLEM' => 'LEGENDARY', 'GRANDMA_WOLF' => 'LEGENDARY', 'GRIFFIN' => 'LEGENDARY',
        'GUARDIAN' => 'MYTHIC', 'HORSE' => 'LEGENDARY', 'HOUND' => 'LEGENDARY',
        'JELLYFISH' => 'LEGENDARY', 'JERRY' => 'MYTHIC', 'KUUDRA' => 'LEGENDARY',
        'LION' => 'LEGENDARY', 'MAGMA_CUBE' => 'LEGENDARY', 'MAMMOTH' => 'LEGENDARY',
        'MEGALODON' => 'LEGENDARY', 'MITHRIL_GOLEM' => 'MYTHIC', 'MOLE' => 'LEGENDARY',
        'MONKEY' => 'LEGENDARY', 'MOOSHROOM_COW' => 'LEGENDARY', 'OCELOT' => 'LEGENDARY',
        'OWL' => 'LEGENDARY', 'PARROT' => 'LEGENDARY', 'PENGUIN' => 'LEGENDARY',
        'PHOENIX' => 'LEGENDARY', 'PIG' => 'LEGENDARY', 'PIGMAN' => 'LEGENDARY',
        'RABBIT' => 'LEGENDARY', 'RAT' => 'MYTHIC', 'REINDEER' => 'LEGENDARY',
        'RIFT_FERRET' => 'LEGENDARY', 'ROCK' => 'LEGENDARY', 'SCATHA' => 'LEGENDARY',
        'SHEEP' => 'LEGENDARY', 'SILVERFISH' => 'LEGENDARY', 'SKELETON' => 'LEGENDARY',
        'SKELETON_HORSE' => 'LEGENDARY', 'SLUG' => 'LEGENDARY', 'SNAIL' => 'LEGENDARY',
        'SNOWMAN' => 'MYTHIC', 'SPIDER' => 'MYTHIC', 'SPINOSAURUS' => 'LEGENDARY',
        'SPIRIT' => 'LEGENDARY', 'SQUID' => 'LEGENDARY', 'SUBZERO_WISP' => 'LEGENDARY',
        'TARANTULA' => 'MYTHIC', 'TIGER' => 'LEGENDARY', 'TURTLE' => 'LEGENDARY',
        'TYRANNOSAURUS' => 'LEGENDARY', 'WITHER_SKELETON' => 'LEGENDARY', 'WOLF' => 'LEGENDARY',
        'ZOMBIE' => 'LEGENDARY', 'AMMONITE' => 'LEGENDARY', 'ANKYLOSAURUS' => 'LEGENDARY',
    ];

    /** Pets that use a typeGroup (wisps share one pet-score slot). */
    private const PET_TYPE_GROUP = [
        'DROPLET_WISP' => 'WISP', 'FROST_WISP' => 'WISP',
        'GLACIAL_WISP' => 'WISP', 'SUBZERO_WISP' => 'WISP',
    ];

    /** Pets excluded from pet score (from SkyCrypt PET_DATA). */
    private const PET_SCORE_EXCLUDED = ['FRACTURED_MONTEZUMA_SOUL'];

    /** Pets exclusive to Bingo mode. */
    private const BINGO_EXCLUSIVE_PETS = ['BINGO'];

    private function parsePets(array $member): array
    {
        $pets   = $member['pets_data']['pets'] ?? [];
        $result = [];

        $tierOrder = ['COMMON', 'UNCOMMON', 'RARE', 'EPIC', 'LEGENDARY', 'MYTHIC'];
        $mcColors  = [
            'COMMON'    => '§f',
            'UNCOMMON'  => '§a',
            'RARE'      => '§9',
            'EPIC'      => '§5',
            'LEGENDARY' => '§6',
            'MYTHIC'    => '§d',
        ];

        foreach ($pets as $pet) {
            $type   = $pet['type'] ?? 'UNKNOWN';
            $tier   = $pet['tier'] ?? 'COMMON';
            $xp     = $pet['exp'] ?? 0;
            $skin   = $pet['skin'] ?? null;
            $active = $pet['active'] ?? false;
            $heldItemId = $pet['heldItem'] ?? null;
            $candyUsed  = $pet['candyUsed'] ?? 0;

            $maxLevel = ($type === 'GOLDEN_DRAGON') ? 200 : 100;
            $levelData = $this->getPetLevel($xp, $tier, $maxLevel);
            $level = $levelData['level'];

            // Display name
            $displayName = ucwords(strtolower(str_replace('_', ' ', $type)));
            $colorCode = $mcColors[$tier] ?? '§f';

            // Texture path (head hash, with cosmetic skin override)
            $headHash = self::PET_HEAD_TEXTURES[$type] ?? null;
            $texturePath = $headHash ? "/head/{$headHash}" : null;

            // Override with cosmetic pet skin texture if equipped
            if ($skin && isset(self::PET_SKINS[$skin])) {
                $texturePath = '/head/' . self::PET_SKINS[$skin];
            }

            // Build lore (MC color-coded)
            $lore = [];
            $lore[] = ''; // empty line after name

            // XP progress
            if ($level < $maxLevel) {
                $xpBar = $this->buildProgressBar($levelData['progress']);
                $lore[] = "§7Progress to Level {$level} §8→ §7" . ($level + 1) . ": §e" . round($levelData['progress'] * 100, 1) . '%';
                $lore[] = $xpBar . " §e" . number_format($levelData['xpCurrent']) . '§6/§e' . number_format($levelData['xpForNext']);
            } else {
                $lore[] = '§bMAX LEVEL';
            }

            // Held item
            $heldItemName = null;
            if ($heldItemId) {
                $itemInfo = self::PET_ITEMS[$heldItemId] ?? null;
                if ($itemInfo) {
                    $itemColor = $mcColors[$itemInfo['tier'] ?? 'COMMON'] ?? '§f';
                    $heldItemName = $itemInfo['name'];
                    $lore[] = '';
                    $lore[] = "§7Held Item: {$itemColor}{$itemInfo['name']}";
                } else {
                    $heldItemName = ucwords(strtolower(str_replace('_', ' ', $heldItemId)));
                    $lore[] = '';
                    $lore[] = "§7Held Item: §f{$heldItemName}";
                }
            }

            // Candy used
            if ($candyUsed > 0) {
                $lore[] = '';
                $lore[] = "§7Candy Used: §d{$candyUsed}";
            }

            // Rarity line
            $lore[] = '';
            $lore[] = "{$colorCode}§l{$tier} PET";

            // Convert lore to HTML
            $loreHtml = array_map([ItemParser::class, 'colorCodeToHtml'], $lore);

            $result[] = [
                'type'         => $type,
                'tier'         => $tier,
                'rarity'       => strtolower($tier),
                'xp'           => $xp,
                'level'        => $levelData,
                'active'       => $active,
                'heldItem'     => $heldItemId,
                'heldItemName' => $heldItemName,
                'skin'         => $skin,
                'candyUsed'    => $candyUsed,
                'name'         => "[Lvl {$level}] {$displayName}",
                'texture_path' => $texturePath,
                'lore_html'    => $loreHtml,
            ];
        }

        // Sort: active first, then by rarity desc, then by XP desc
        usort($result, function ($a, $b) use ($tierOrder) {
            if ($a['active'] && ! $b['active']) return -1;
            if (! $a['active'] && $b['active']) return 1;
            $ra = array_search($a['tier'], $tierOrder);
            $rb = array_search($b['tier'], $tierOrder);
            if ($ra !== $rb) return $rb <=> $ra;
            return $b['xp'] <=> $a['xp'];
        });

        // ── Build structured output ────────────────────────────────
        // Unique pets (one per type, highest rarity first)
        $uniqueTypes = [];
        $uniquePets  = [];
        $otherPets   = [];
        foreach ($result as $pet) {
            if (! isset($uniqueTypes[$pet['type']])) {
                $uniqueTypes[$pet['type']] = true;
                $uniquePets[] = $pet;
            } else {
                $otherPets[] = $pet;
            }
        }

        // Pet score
        $petScore = $this->calculatePetScore($result, $tierOrder);

        // Missing pets
        $missingPets = $this->getMissingPets($result, $mcColors, $tierOrder);

        // Unique pet skins count (non-null skins)
        $skinSet = [];
        foreach ($result as $p) {
            if ($p['skin']) $skinSet[$p['skin']] = true;
        }

        // Total candy used
        $totalCandy = array_sum(array_column($result, 'candyUsed'));

        // Total pet XP
        $totalXp = array_sum(array_column($result, 'xp'));

        return [
            'pets'          => $result,
            'uniquePets'    => $uniquePets,
            'otherPets'     => $otherPets,
            'missing'       => $missingPets,
            'amount'        => count($uniqueTypes),
            'total'         => count(self::PET_MAX_TIER),
            'amountSkins'   => count($skinSet),
            'petScore'      => $petScore,
            'totalCandy'    => $totalCandy,
            'totalPetXp'    => $totalXp,
        ];
    }

    /**
     * Calculate pet score (from SkyCrypt getPetScore logic).
     * Each unique pet type contributes its highest rarity value.
     * Each max-level pet adds +1.
     */
    private function calculatePetScore(array $pets, array $tierOrder): array
    {
        $highestRarity = [];
        $highestLevel  = [];

        foreach ($pets as $pet) {
            $type = $pet['type'];
            if (in_array($type, self::PET_SCORE_EXCLUDED, true)) continue;

            $group = self::PET_TYPE_GROUP[$type] ?? $type;
            $rarity = strtolower($pet['tier']);
            $value = self::PET_VALUE[$rarity] ?? 0;

            if (! isset($highestRarity[$group]) || $value > $highestRarity[$group]) {
                $highestRarity[$group] = $value;
            }

            $maxLevel = ($type === 'GOLDEN_DRAGON') ? 200 : 100;
            if ($pet['level']['level'] >= $maxLevel) {
                $highestLevel[$group] = 1;
            }
        }

        $total = array_sum($highestRarity) + array_sum($highestLevel);

        // Find MF bonus from PET_REWARDS
        $magicFind = 0;
        foreach (array_reverse(self::PET_REWARDS, true) as $threshold => $mf) {
            if ($total >= $threshold) {
                $magicFind = $mf;
                break;
            }
        }

        return [
            'total'     => $total,
            'magicFind' => $magicFind,
        ];
    }

    /**
     * Get list of missing pets (pet types not owned by the player).
     */
    private function getMissingPets(array $ownedPets, array $mcColors, array $tierOrder): array
    {
        $ownedGroups = [];
        foreach ($ownedPets as $pet) {
            $group = self::PET_TYPE_GROUP[$pet['type']] ?? $pet['type'];
            $ownedGroups[$group] = true;
        }

        $missing = [];
        foreach (self::PET_MAX_TIER as $type => $maxTier) {
            if (in_array($type, self::BINGO_EXCLUSIVE_PETS, true)) continue;

            $group = self::PET_TYPE_GROUP[$type] ?? $type;
            if (isset($ownedGroups[$group])) continue;
            // Only add one representative per group
            if (isset($missing[$group])) continue;

            $displayName = ucwords(strtolower(str_replace('_', ' ', $type)));
            $headHash = self::PET_HEAD_TEXTURES[$type] ?? null;
            $colorCode = $mcColors[$maxTier] ?? '§f';

            $missing[$group] = [
                'type'         => $type,
                'tier'         => $maxTier,
                'rarity'       => strtolower($maxTier),
                'name'         => $displayName,
                'texture_path' => $headHash ? "/head/{$headHash}" : null,
                'lore_html'    => [ItemParser::colorCodeToHtml("{$colorCode}§l{$maxTier} PET")],
            ];
        }

        // Sort missing by rarity desc, then alphabetically
        $missingArr = array_values($missing);
        usort($missingArr, function ($a, $b) use ($tierOrder) {
            $ra = array_search($a['tier'], $tierOrder);
            $rb = array_search($b['tier'], $tierOrder);
            if ($ra !== $rb) return $rb <=> $ra;
            return strcmp($a['name'], $b['name']);
        });

        return $missingArr;
    }

    /**
     * Build a Minecraft-style progress bar (like SkyCrypt).
     */
    private function buildProgressBar(float $progress): string
    {
        $total = 20;
        $filled = (int) round($progress * $total);
        $empty  = $total - $filled;
        return '§2' . str_repeat('-', $filled) . '§f' . str_repeat('-', $empty);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detailed XP → level with progress info (for progress bars).
     */
    private function xpToLevelDetailed(float $xp, array $table, int $maxLevel): array
    {
        $level = 0;
        $cap   = min($maxLevel, count($table) - 1);

        for ($i = $cap; $i >= 0; $i--) {
            if ($xp >= $table[$i]) {
                $level = $i;
                break;
            }
        }

        if ($level >= $cap) {
            return [
                'level'     => $level,
                'xpCurrent' => round($xp - ($table[$level] ?? 0), 2),
                'xpForNext' => 0,
                'progress'  => 1,
            ];
        }

        $xpCurrent = $xp - $table[$level];
        $xpForNext = ($table[$level + 1] ?? $table[$level]) - $table[$level];
        $progress  = $xpForNext > 0 ? min($xpCurrent / $xpForNext, 1) : 1;

        return [
            'level'     => $level,
            'xpCurrent' => round($xpCurrent, 2),
            'xpForNext' => round($xpForNext, 2),
            'progress'  => round($progress, 4),
        ];
    }

    /**
     * Convert XP to level using an XP table (simple integer return).
     */
    private function xpToLevel(float $xp, array $table, int $maxLevel): int
    {
        $level = 0;
        $cap   = min($maxLevel, count($table) - 1);

        for ($i = $cap; $i >= 0; $i--) {
            if ($xp >= $table[$i]) {
                $level = $i;
                break;
            }
        }

        return $level;
    }

    /**
     * Resolve Minecraft username → UUID via Mojang API.
     */
    private function getUuidFromMojang(string $username): ?string
    {
        try {
            $resp = Http::timeout(10)->get(
                'https://api.mojang.com/users/profiles/minecraft/' . urlencode($username)
            );

            if (! $resp->successful()) {
                return null;
            }

            return $resp->json('id') ?: null;
        } catch (\Exception $e) {
            Log::warning('Mojang API exception', [
                'username'  => $username,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Cache helpers ───────────────────────────────────────────────

    private function cacheGet(string $key): mixed
    {
        $store = config('cache.default', 'file');
        try {
            return Cache::store($store)->get($key);
        } catch (\Throwable $e) {
            Log::warning('Cache get failed, trying file store', ['exception' => $e->getMessage()]);
            try {
                return Cache::store('file')->get($key);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function cachePut(string $key, mixed $value): void
    {
        $store = config('cache.default', 'file');
        try {
            Cache::store($store)->put($key, $value, self::CACHE_TTL);
        } catch (\Throwable $e) {
            Log::warning('Cache put failed, trying file store', ['exception' => $e->getMessage()]);
            try {
                Cache::store('file')->put($key, $value, self::CACHE_TTL);
            } catch (\Throwable) {
                // silently fail
            }
        }
    }

    /**
     * Recursively sanitize all strings in an array to valid UTF-8
     * so json_encode won't throw "Malformed UTF-8" errors.
     */
    private function sanitizeForJson(mixed $data): mixed
    {
        if (is_string($data)) {
            if (mb_check_encoding($data, 'UTF-8')) {
                return $data;
            }
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $safeKey = is_string($key) ? $this->sanitizeForJson($key) : $key;
                $result[$safeKey] = $this->sanitizeForJson($value);
            }
            return $result;
        }

        return $data;
    }

    // ─── Player Stats (SkyCrypt-style aggregation) ──────────────────

    /**
     * Calculate total player stats from base, skills, fairy souls, and equipped gear.
     * Mirrors SkyCrypt's stat aggregation approach.
     */
    private function calculatePlayerStats(array $member, array $skills, array $armor, array $equipment, array $accessories): array
    {
        // Base stats every player starts with
        $stats = [
            'Health'            => 100,
            'Defense'           => 0,
            'Strength'          => 0,
            'Speed'             => 100,
            'Critical Chance'   => 30,
            'Critical Damage'   => 50,
            'Intelligence'      => 0,
            'Attack Speed'      => 0,
            'Ability Damage'    => 0,
            'Magic Find'        => 0,
            'Pet Luck'          => 0,
            'True Defense'      => 0,
            'Ferocity'          => 0,
            'Sea Creature Chance'=> 20,
            'Health Regen'      => 100,
            'Vitality'          => 0,
            'Mending'           => 0,
            'Fishing Speed'     => 0,
            'Mining Speed'      => 0,
            'Mining Fortune'    => 0,
            'Farming Fortune'   => 0,
            'Foraging Fortune'  => 0,
        ];

        // ── Skill bonuses (simplified SkyCrypt logic) ──
        $skillStatBonuses = [
            'farming'     => ['Health' => 2, 'Farming Fortune' => 4],
            'mining'      => ['Defense' => 1, 'Mining Fortune' => 4, 'Mining Speed' => 20],
            'combat'      => ['Critical Chance' => 0.5, 'Critical Damage' => 0],  // CD comes from separate table
            'foraging'    => ['Strength' => 1, 'Foraging Fortune' => 4],
            'fishing'     => ['Health' => 2, 'Sea Creature Chance' => 0.2],
            'enchanting'  => ['Intelligence' => 1],
            'alchemy'     => ['Intelligence' => 1],
            'taming'      => ['Pet Luck' => 1],
            'carpentry'   => [],
            'runecrafting' => [],
            'social'      => [],
        ];

        foreach ($skills as $name => $skillData) {
            $level = $skillData['level'] ?? 0;
            if (!isset($skillStatBonuses[$name])) continue;

            foreach ($skillStatBonuses[$name] as $statName => $perLevel) {
                $stats[$statName] = ($stats[$statName] ?? 0) + ($perLevel * $level);
            }
        }

        // Combat special: +1 CD per level from 1-50
        $combatLevel = $skills['combat']['level'] ?? 0;
        $stats['Critical Damage'] += $combatLevel;

        // ── Fairy soul bonuses (exchanges) ──
        $fairySouls = $member['fairy_soul']['total_collected'] ?? $member['fairy_exchanges'] ?? 0;
        $exchanges  = (int) floor($fairySouls / 5);

        // Each exchange gives: +3 HP, +1 Def, +1 Str, +1 Spd (approximately, simplified)
        $stats['Health']   += $exchanges * 3;
        $stats['Defense']  += $exchanges * 1;
        $stats['Strength'] += $exchanges * 1;
        $stats['Speed']    += (int) floor($exchanges * 0.5);

        // ── Item stats from armor, equipment, accessories ──
        $this->addItemStatsToTotal($stats, $armor);
        $this->addItemStatsToTotal($stats, $equipment);
        $this->addItemStatsToTotal($stats, $accessories);

        // Convert to frontend format with icons and colors
        return $this->formatPlayerStats($stats);
    }

    /**
     * Sum item stats into total stats array.
     */
    private function addItemStatsToTotal(array &$totals, array $items): void
    {
        // Mapping from abbreviation back to full stat name
        $abbrevToFull = [
            'HP'    => 'Health',
            'Def'   => 'Defense',
            'Str'   => 'Strength',
            'Spd'   => 'Speed',
            'CC'    => 'Critical Chance',
            'CD'    => 'Critical Damage',
            'Int'   => 'Intelligence',
            'AS'    => 'Attack Speed',
            'AD'    => 'Ability Damage',
            'MF'    => 'Magic Find',
            'PL'    => 'Pet Luck',
            'TD'    => 'True Defense',
            'FS'    => 'Ferocity',
            'SCC'   => 'Sea Creature Chance',
            'HPR'   => 'Health Regen',
            'Vit'   => 'Vitality',
            'Mnd'   => 'Mending',
            'FshSpd'=> 'Fishing Speed',
            'MnSpd' => 'Mining Speed',
            'MnFrt' => 'Mining Fortune',
            'FmFrt' => 'Farming Fortune',
            'FgFrt' => 'Foraging Fortune',
            'Dmg'   => 'Damage',
        ];

        foreach ($items as $item) {
            if (!$item || !isset($item['stats'])) continue;
            foreach ($item['stats'] as $abbrev => $stat) {
                $fullName = $abbrevToFull[$abbrev] ?? null;
                if (!$fullName) continue;
                if (!isset($totals[$fullName])) $totals[$fullName] = 0;
                $totals[$fullName] += $stat['value'] ?? 0;
            }
        }
    }

    /**
     * Format stats for frontend display with SkyCrypt-style icons and colors.
     */
    private function formatPlayerStats(array $stats): array
    {
        $statConfig = [
            'Health'             => ['icon' => '❤', 'color' => '#FF5555'],
            'Defense'            => ['icon' => '🛡', 'color' => '#55FF55'],
            'Strength'           => ['icon' => '💪', 'color' => '#FF5555'],
            'Speed'              => ['icon' => '✈', 'color' => '#FFFFFF'],
            'Critical Chance'    => ['icon' => '☠', 'color' => '#FF5555', 'suffix' => '%'],
            'Critical Damage'    => ['icon' => '☠', 'color' => '#FF5555', 'suffix' => '%'],
            'Intelligence'       => ['icon' => '✨', 'color' => '#55FFFF'],
            'Attack Speed'       => ['icon' => '⚡', 'color' => '#FFFF55', 'suffix' => '%'],
            'Ability Damage'     => ['icon' => '🔥', 'color' => '#FF5555', 'suffix' => '%'],
            'Magic Find'         => ['icon' => '⭐', 'color' => '#55FFFF'],
            'Pet Luck'           => ['icon' => '♣', 'color' => '#FF55FF'],
            'True Defense'       => ['icon' => '◎', 'color' => '#FFFFFF'],
            'Ferocity'           => ['icon' => '⚔', 'color' => '#FF5555'],
            'Sea Creature Chance'=> ['icon' => '🌊', 'color' => '#55FFFF', 'suffix' => '%'],
            'Health Regen'       => ['icon' => '❤', 'color' => '#FF5555'],
            'Vitality'           => ['icon' => '🍀', 'color' => '#FF55FF'],
            'Mending'            => ['icon' => '❤', 'color' => '#55FF55'],
            'Fishing Speed'      => ['icon' => '🎣', 'color' => '#55FFFF'],
            'Mining Speed'       => ['icon' => '⛏', 'color' => '#FFFF55'],
            'Mining Fortune'     => ['icon' => '⛏', 'color' => '#FFAA00'],
            'Farming Fortune'    => ['icon' => '🌾', 'color' => '#FFAA00'],
            'Foraging Fortune'   => ['icon' => '🌲', 'color' => '#FFAA00'],
        ];

        $result = [];
        foreach ($stats as $name => $value) {
            $value = (int) round($value);
            if ($value === 0 && !in_array($name, ['Health', 'Defense', 'Speed', 'Critical Chance', 'Critical Damage'])) {
                continue;  // Skip zero-value non-essential stats
            }
            $cfg = $statConfig[$name] ?? ['icon' => '•', 'color' => '#AAAAAA'];
            $result[] = [
                'name'   => $name,
                'value'  => $value,
                'icon'   => $cfg['icon'],
                'color'  => $cfg['color'],
                'suffix' => $cfg['suffix'] ?? '',
            ];
        }
        return $result;
    }
}
