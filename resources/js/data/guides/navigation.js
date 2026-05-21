/** Sidebar + homepage card groups (SkyResources structure). */
export const guideGroups = [
    {
        id: 'progression',
        label: 'Progression',
        topics: [
            { slug: 'early', title: 'Early Game', description: 'Structured start: priorities, money making, and must-have resources.' },
            { slug: 'mid', title: 'Mid Game', description: 'Optimization phase: scale your setup and pick efficient lanes.' },
            { slug: 'end', title: 'End Game', description: 'Meta + efficiency: high-end methods, updates, and min-max resources.' },
            { slug: 'skills', title: 'Skills', description: 'All skills overview, best XP methods, and milestone rewards.' },
            { slug: 'accessories', title: 'Accessories & MP', description: 'Magical Power deep dive, accessory bags, tuning, and power stones.' },
        ],
    },
    {
        id: 'game-systems',
        label: 'Game Systems',
        topics: [
            { slug: 'garden', title: 'Garden', description: 'Crop milestones, FF basics, best tools, pest farming & profit checks.' },
            { slug: 'mining', title: 'Mining', description: 'HotM pathing, powder, gemstones, gear tiers, and drill upgrades.' },
            { slug: 'dungeons', title: 'Dungeons', description: 'Floor progression, classes, gear paths, secrets, and tutorials.' },
            { slug: 'slayers', title: 'Slayers', description: 'All 6 bosses, tier requirements, gear, RNG drops, and costs.' },
            { slug: 'pets', title: 'Pets', description: 'Best pets per activity, leveling methods, pet items, and pet score.' },
            { slug: 'fishing', title: 'Fishing', description: 'Regular, lava, trophy fishing — gear, sea creatures, and profit.' },
            { slug: 'enchanting', title: 'Enchanting', description: 'Best enchants per gear type, experimentation table, and XP methods.' },
            { slug: 'crimson', title: 'Crimson Isle', description: 'Faction choice, reputation grind, dojo, and Kuudra intro.' },
            { slug: 'kuudra', title: 'Kuudra', description: 'All 5 tiers, gear requirements, party roles, and Crimson armor.' },
            { slug: 'rift', title: 'The Rift', description: 'Timecharms, motes farming, enigma souls, and vampire slayer.' },
            { slug: 'shards', title: 'Shards & Hunting', description: 'Best locations, drops, routes, and tracking tools.' },
        ],
    },
    {
        id: 'economy',
        label: 'Economy & Tools',
        topics: [
            { slug: 'money', title: 'Money Making', description: 'Farming, mining, flipping, mayor prep — pick your method.' },
            { slug: 'collections', title: 'Collections & Minions', description: 'Important unlocks, best minion setups, and passive income.' },
            { slug: 'mayor', title: 'Mayors & Events', description: 'All mayors & perks, election cycle, and prep strategies.' },
        ],
    },
    {
        id: 'meta',
        label: 'Meta & Info',
        topics: [
            { slug: 'tricks', title: 'Tricks & Tips', description: 'Small habits and mechanics tips that make you progress faster.' },
            { slug: 'mods', title: 'Mods', description: 'Curated essential mods (SkyHanni, QoL, performance) and safe sources.' },
            { slug: 'news', title: 'News & Patches', description: 'Patch notes, what changed, who it affects.' },
        ],
    },
];

export const guideExternalTools = [
    { label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/' },
    { label: 'COFL Sky', url: 'https://sky.coflnet.com/' },
    { label: 'Fandom Wiki', url: 'https://hypixel-skyblock.fandom.com/' },
];

export const guideQuickLinks = [
    { prompt: 'Checking prices / flipping?', label: 'COFL', url: 'https://sky.coflnet.com/' },
    { prompt: 'Mechanics lookup?', label: 'Hypixel Wiki', url: 'https://wiki.hypixel.net/' },
    { prompt: 'Detailed wiki?', label: 'Fandom Wiki', url: 'https://hypixel-skyblock.fandom.com/' },
    { prompt: 'Farming calculator?', label: 'SkyblockTools.dev', url: 'https://skyblocktools.dev/farming-profit-calculator' },
    { prompt: 'Farming leaderboards?', label: 'EliteFarmers', url: 'https://elitefarmers.com/' },
];

export const allGuideTopics = guideGroups.flatMap((g) =>
    g.topics.map((t) => ({ ...t, category: g.id, categoryLabel: g.label })),
);

export const guideTopicSlugs = allGuideTopics.map((t) => t.slug);
