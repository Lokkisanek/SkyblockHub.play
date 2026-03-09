# SkyCrypt / Hypixel proxy — JSON struktura

Endpoint: `GET /api/skycrypt/{username}`

Lokální proxy volá Hypixel `v2/skyblock/profiles` a transformuje data tak,
aby frontend (`ProfileStats`) dostal pole `profiles` se strukturou podobnou SkyCrypt.

---

## Top-level

```json
{
  "source": "api",          // "api" | "cache"
  "data": {
    "uuid": "adcc44f1...",   // Mojang UUID hráče
    "username": "Lokkisanecek",
    "profiles": { ... }
  }
}
```

## Profil

Každý klíč v `data.profiles` je `profile_id` → objekt:

| Pole | Typ | Popis |
|------|-----|-------|
| `cute_name` | string | Jméno profilu (Pomegranate, Raspberry…) |
| `selected` | boolean | Aktivní profil |
| `game_mode` | string | `"normal"`, `"bingo"`, `"ironman"` … |
| `data` | object | Hlavní statistiky (viz níže) |

## `data` — statistiky profilu

### `skyblock_level`

| Pole | Typ | Popis |
|------|-----|-------|
| `level` | int | SkyBlock level (floor(totalXp / 100)) |
| `xpCurrent` | float | XP v rámci aktuálního levelu |
| `xpForNext` | int | XP potřeba pro další level (100) |
| `progress` | float | 0-1 progress |
| `totalXp` | float | Celkové nahromaděné XP |

### `fairy_souls` — int

Počet sebraných fairy souls.

### `first_join` — int|null

Unix timestamp (ms) prvního přihlášení do profilu.

### `average_skill_level` — float

Průměr úrovní skills (bez runecraftingu a social).

### `skills`

Objekt: `{ farming, mining, combat, foraging, fishing, enchanting, alchemy, carpentry, taming, runecrafting, social }`

Každý skill:

| Pole | Typ | Popis |
|------|-----|-------|
| `level` | int | Aktuální level |
| `maxLevel` | int | Maximální level (25 nebo 60) |
| `xp` | float | Celkové XP |
| `xpCurrent` | float | XP v aktuálním levelu |
| `xpForNext` | float | XP potřeba do dalšího levelu |
| `progress` | float | 0-1 progress bar |

### `slayers`

Objekt: `{ zombie, spider, wolf, enderman, blaze, vampire }`

Každý slayer:

| Pole | Typ | Popis |
|------|-----|-------|
| `xp` | int | Celkové slayer XP |
| `level.currentLevel` | int | Aktuální level |
| `level.maxLevel` | int | Max level |

### `dungeons`

| Pole | Typ | Popis |
|------|-----|-------|
| `catacombs.level.level` | int | Catacombs level |
| `catacombs.level.xp` | float | Celkové XP |
| `catacombs.level.xpCurrent` | float | XP v aktuálním levelu |
| `catacombs.level.xpForNext` | float | XP do dalšího levelu |
| `catacombs.level.progress` | float | 0-1 |
| `secrets_found` | int | Počet secrets |
| `selected_class` | string|null | Vybraná třída |
| `classes` | object | healer, mage, berserk, archer, tank |

Každá třída (`classes.*`):

| Pole | Typ | Popis |
|------|-----|-------|
| `level` | int | Level třídy |
| `xp` | float | Celkové XP |
| `progress` | float | 0-1 |

### `networth`

| Pole | Typ | Popis |
|------|-----|-------|
| `networth` | float | Purse + Bank (základní odhad) |
| `purse` | float | Mince v kapse |
| `bank` | float | Mince v bance |

### `pets`

Pole objektů, seřazené: `active` první, pak podle XP sestupně.

| Pole | Typ | Popis |
|------|-----|-------|
| `type` | string | Typ peta (ENDERMAN, BEE…) |
| `tier` | string | COMMON / UNCOMMON / RARE / EPIC / LEGENDARY / MYTHIC |
| `xp` | float | Nahromaděné XP |
| `active` | boolean | Aktivní pet |
| `heldItem` | string|null | Held item ID |
| `skin` | string|null | Cosmetic skin |
| `candyUsed` | int | Počet použitých candy |

---

## Kompletní příklad (zkráceno)

```json
{
  "source": "api",
  "data": {
    "uuid": "adcc44f1479b447a817842b7f77e94e2",
    "username": "Lokkisanecek",
    "profiles": {
      "abc123-def456": {
        "cute_name": "Pomegranate",
        "selected": true,
        "game_mode": "normal",
        "data": {
          "skyblock_level": {
            "level": 195,
            "xpCurrent": 58,
            "xpForNext": 100,
            "progress": 0.58,
            "totalXp": 19558
          },
          "fairy_souls": 239,
          "first_join": 1647123456000,
          "average_skill_level": 41.78,
          "skills": {
            "farming": { "level": 47, "maxLevel": 60, "xp": 46093575, "xpCurrent": 2020750, "xpForNext": 3400000, "progress": 0.5943 },
            "mining":  { "level": 43, "maxLevel": 60, "xp": 33958041, "xpCurrent": 1235616, "xpForNext": 2600000, "progress": 0.4752 }
          },
          "slayers": {
            "zombie":   { "xp": 106362, "level": { "currentLevel": 7, "maxLevel": 9 } },
            "enderman": { "xp": 25563,  "level": { "currentLevel": 6, "maxLevel": 9 } }
          },
          "dungeons": {
            "catacombs": {
              "level": { "level": 27, "xp": 1395783, "xpCurrent": 156143, "xpForNext": 800000, "progress": 0.1952 }
            },
            "secrets_found": 1177,
            "selected_class": "mage",
            "classes": {
              "healer":  { "level": 20, "xp": 97640, "progress": 0.0 },
              "mage":    { "level": 25, "xp": 259640, "progress": 0.0 },
              "berserk": { "level": 15, "xp": 25340, "progress": 0.0 },
              "archer":  { "level": 10, "xp": 4385, "progress": 0.0 },
              "tank":    { "level": 12, "xp": 8940, "progress": 0.0 }
            }
          },
          "networth": { "networth": 365332838, "purse": 337902838, "bank": 27430000 },
          "pets": [
            { "type": "ENDERMAN", "tier": "LEGENDARY", "xp": 12345678, "active": true, "heldItem": "PET_ITEM_TEXTBOOK", "skin": null, "candyUsed": 0 },
            { "type": "GOLDEN_DRAGON", "tier": "LEGENDARY", "xp": 9000000, "active": false, "heldItem": null, "skin": null, "candyUsed": 0 }
          ]
        }
      }
    }
  }
}
```

---

## Co z dat lze získat

- **Skill level & progress** — vizualizace progress barů (jako SkyCrypt)
- **SkyBlock level** — celkový level hráče
- **Fairy Souls** — kolik jich hráč sebral (max 267 ve vanille)
- **Slayer progres** — aktuální a max level pro každého bossa
- **Dungeon statistiky** — Catacombs level, class levels, secrets
- **Networth** — purse + bank (rozšířitelné o item valuation)
- **Pets** — seznam petů s tier barvami, XP, aktivním petem
- **Porovnání profilů** — hráč může mít více profilů (Normal, Ironman, Bingo…)

## FurSky Reborn textury

Viz [furfsky_reborn_setup.md](furfsky_reborn_setup.md) pro nastavení item textur.
