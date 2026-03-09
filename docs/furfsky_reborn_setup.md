# FurSky Reborn — nastavení textur

Tento dokument popisuje, jak nainstalovat textury z resource packu
[FurSky Reborn](https://github.com/furfsky/reborn) do webové aplikace,
aby se zobrazovaly Minecraft item ikony místo emoji fallbacku.

## 1. Stažení resource packu

1. Přejděte na [https://github.com/furfsky/reborn/releases](https://github.com/furfsky/reborn/releases)
2. Stáhněte nejnovější verzi (např. `FurfSky_Reborn_v1.9.2_full.zip`)

## 2. Rozbalení

Rozbalte `.zip` soubor. Uvnitř najdete adresářovou strukturu Minecraft resource packu:

```
assets/
  minecraft/
    textures/
      item/
        golden_hoe.png
        stone_pickaxe.png
        stone_sword.png
        ...
      block/
        crafting_table_front.png
        enchanting_table_top.png
        ...
```

## 3. Kopírování textur do projektu

Zkopírujte vybrané `.png` soubory z `assets/minecraft/textures/item/` a `assets/minecraft/textures/block/`
do adresáře `public/img/textures/` v projektu.

```powershell
# Příklad (Windows PowerShell)
$src = "C:\stazene\FurfSky_Reborn\assets\minecraft\textures"
$dest = "public\img\textures"

New-Item -ItemType Directory -Force $dest

# Kopírovat item textures
Copy-Item "$src\item\golden_hoe.png"          $dest\
Copy-Item "$src\item\stone_pickaxe.png"       $dest\
Copy-Item "$src\item\stone_sword.png"         $dest\
Copy-Item "$src\item\fishing_rod_uncast.png"  $dest\
Copy-Item "$src\item\brewing_stand.png"       $dest\
Copy-Item "$src\item\magma_cream.png"         $dest\
Copy-Item "$src\item\emerald.png"             $dest\
Copy-Item "$src\item\rotten_flesh.png"        $dest\
Copy-Item "$src\item\spider_eye.png"          $dest\
Copy-Item "$src\item\bone.png"               $dest\
Copy-Item "$src\item\ender_pearl.png"         $dest\
Copy-Item "$src\item\blaze_rod.png"           $dest\
Copy-Item "$src\item\iron_sword.png"          $dest\
Copy-Item "$src\item\spawn_egg.png"           $dest\
Copy-Item "$src\item\redstone.png"            $dest\

# Block textures (pro enchanting table, crafting table apod.)
Copy-Item "$src\block\enchanting_table_top.png" $dest\
Copy-Item "$src\block\crafting_table_front.png" $dest\

# Splash potion (pro healer class)
Copy-Item "$src\item\potion_bottle_splash.png"  $dest\
```

## 4. Jak to funguje

Utilita `resources/js/utils/textures.js` při načtení stránky zkontroluje,
jestli jsou textury v `public/img/textures/` dostupné.

- **Pokud ano** → zobrazí se obrázek jako `<img>` tag (Minecraft ikona)
- **Pokud ne** → zobrazí se emoji fallback (🌾, ⛏️, ⚔️ atd.)

Není potřeba měnit kód — stačí do složky nakopírovat správné PNG soubory.

## 5. Seznam potřebných textur

| Soubor                        | Použití                        |
|-------------------------------|--------------------------------|
| `golden_hoe.png`              | Skill: Farming                 |
| `stone_pickaxe.png`           | Skill: Mining                  |
| `stone_sword.png`             | Skill: Combat                  |
| `jungle_sapling.png`          | Skill: Foraging                |
| `fishing_rod_uncast.png`      | Skill: Fishing                 |
| `enchanting_table_top.png`    | Skill: Enchanting              |
| `brewing_stand.png`           | Skill: Alchemy                 |
| `crafting_table_front.png`    | Skill: Carpentry               |
| `spawn_egg.png`               | Skill: Taming                  |
| `magma_cream.png`             | Skill: Runecrafting            |
| `emerald.png`                 | Skill: Social                  |
| `rotten_flesh.png`            | Slayer: Zombie                 |
| `spider_eye.png`              | Slayer: Spider                 |
| `bone.png`                    | Slayer: Wolf                   |
| `ender_pearl.png`             | Slayer: Enderman               |
| `blaze_rod.png`               | Slayer: Blaze / Mage class     |
| `redstone.png`                | Slayer: Vampire                |
| `iron_sword.png`              | Class: Berserk                 |
| `potion_bottle_splash.png`    | Class: Healer                  |
| `leather_chestplate.png`      | Class: Tank                    |

## 6. Rozšíření

Pro další ikony (armor, pets, items v inventáři) přidejte do:
1. `resources/js/utils/textures.js` — nový mapovací objekt
2. `public/img/textures/` — odpovídající PNG soubor

Textury by měly být **16×16 px** (nebo násobky) a ve formátu **PNG**.
