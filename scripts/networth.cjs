/**
 * Node.js script to calculate SkyBlock profile networth using SkyHelper-Networth.
 *
 * Called from PHP via:
 *   echo $json | node scripts/networth.cjs
 *
 * Input (JSON on stdin):
 *   { profileData, museumData, bankBalance }
 *
 * Output (JSON on stdout):
 *   {
 *     networth, unsoulboundNetworth, purse, bank, personalBank, noInventory,
 *     categories: { armor: { total, unsoulboundTotal }, ... },
 *     itemPricesByUuid: { "<uuid>": { price, soulbound }, ... },
 *     itemPricesById: { "<skyblock_id>": [{ price, soulbound }], ... }
 *   }
 */

const { ProfileNetworthCalculator, NetworthManager } = require('skyhelper-networth');

let input = '';

process.stdin.setEncoding('utf8');
process.stdin.on('data', (chunk) => {
    input += chunk;
});

process.stdin.on('end', async () => {
    try {
        const { profileData, museumData, bankBalance } = JSON.parse(input);

        if (!profileData) {
            throw new Error('profileData is required');
        }

        // Initialize items from Hypixel API (needed for item info like upgrade costs)
        await NetworthManager.updateItems();

        // Create calculator and compute networth
        const calculator = new ProfileNetworthCalculator(
            profileData,
            museumData || {},
            bankBalance ?? 0
        );

        const result = await calculator.getNetworth({
            stackItems: false,
            sortItems: false,
            cachePrices: true,
            includeItemData: true,
        });

        // Build item value maps:
        // 1. By UUID (most reliable, unique per item instance)
        // 2. By skyblock_id as fallback (for items without UUID, e.g. pets)
        const itemPricesByUuid = {};
        const itemPricesById = {};

        for (const [category, data] of Object.entries(result.types || {})) {
            if (!data.items) continue;
            for (const item of data.items) {
                if (!item || item.price <= 0) continue;

                const priceEntry = {
                    price: item.price,
                    soulbound: item.soulbound || false,
                };

                // Try to extract UUID from the original item data
                // SkyHelper-Networth stores raw NBT as item.item (not item.itemData)
                const uuid = item.item?.tag?.ExtraAttributes?.uuid;
                if (uuid) {
                    itemPricesByUuid[uuid] = priceEntry;
                }

                // Also store by skyblock_id as fallback
                if (item.id) {
                    if (!itemPricesById[item.id]) {
                        itemPricesById[item.id] = [];
                    }
                    itemPricesById[item.id].push(priceEntry);
                }
            }
        }

        // Build category totals
        const categories = {};
        for (const [key, val] of Object.entries(result.types || {})) {
            categories[key] = {
                total: val.total || 0,
                unsoulboundTotal: val.unsoulboundTotal || 0,
            };
        }

        const output = {
            networth: result.networth || 0,
            unsoulboundNetworth: result.unsoulboundNetworth || 0,
            purse: result.purse || 0,
            bank: result.bank || 0,
            personalBank: result.personalBank || 0,
            noInventory: result.noInventory || false,
            categories,
            itemPricesByUuid,
            itemPricesById,
        };

        process.stdout.write(JSON.stringify(output));
        process.exit(0);
    } catch (err) {
        process.stderr.write(JSON.stringify({ error: err.message || String(err) }));
        process.exit(1);
    }
});
