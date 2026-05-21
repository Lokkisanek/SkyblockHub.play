<?php

namespace App\Services;

/**
 * Maps the active SkyBlock mayor (and perks) to money-making multipliers.
 */
class MoneyMakingMayorBoostService
{
    public function __construct(
        private readonly MayorService $mayorService,
        private readonly PerkService $perkService,
    ) {}

    /**
     * @return array{
     *   name: string,
     *   perks: array<int, array{name: string, description: ?string}>,
     *   multipliers: array<string, float>,
     *   active_boosts: array<int, array{label: string, detail: string, activities: list<string>}>,
     *   bazaar_instant_sell_tax_rate: float
     * }
     */
    public function resolve(?array $mayorPayload = null): array
    {
        $payload = $mayorPayload ?? $this->mayorService->getCurrentMayorData();
        $name = (string) ($payload['name'] ?? 'Unknown');
        $perks = (array) ($payload['perks'] ?? []);
        $blob = strtolower($name);

        foreach ($perks as $perk) {
            if (! is_array($perk)) {
                continue;
            }
            $blob .= ' '.strtolower((string) ($perk['name'] ?? ''));
            $blob .= ' '.strtolower((string) ($perk['description'] ?? ''));
        }

        $multipliers = $this->baseMultipliers();
        $activeBoosts = [];

        $this->applyFinnegan($name, $blob, $multipliers, $activeBoosts);
        $this->applyCole($name, $blob, $multipliers, $activeBoosts);
        $this->applyMarina($name, $blob, $multipliers, $activeBoosts);
        $this->applyPaul($name, $blob, $multipliers, $activeBoosts);
        $this->applyAatrox($name, $blob, $multipliers, $activeBoosts);
        $this->applyDiaz($name, $blob, $multipliers, $activeBoosts);
        $this->applyDiana($name, $blob, $multipliers, $activeBoosts);
        $this->applyDerpy($name, $blob, $multipliers, $activeBoosts);
        $this->applyFoxy($name, $blob, $multipliers, $activeBoosts);
        $this->applyJerry($name, $blob, $multipliers, $activeBoosts);

        return [
            'name' => $name,
            'perks' => $perks,
            'multipliers' => $multipliers,
            'active_boosts' => $activeBoosts,
            'bazaar_instant_sell_tax_rate' => $this->perkService->getInstantSellBazaarTaxRate($payload),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function baseMultipliers(): array
    {
        return [
            'farming' => 1.0,
            'garden' => 1.0,
            'mining' => 1.0,
            'foraging' => 1.0,
            'fishing' => 1.0,
            'dungeons' => 1.0,
            'slayer' => 1.0,
            'zealots' => 1.0,
        ];
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function bump(array &$multipliers, array $activities, float $factor, string $label, string $detail, array &$activeBoosts): void
    {
        foreach ($activities as $activity) {
            if (! isset($multipliers[$activity])) {
                continue;
            }
            $multipliers[$activity] *= $factor;
        }

        $activeBoosts[] = [
            'label' => $label,
            'detail' => $detail,
            'activities' => $activities,
        ];
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyFinnegan(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'finnegan')
            && ! str_contains($blob, 'blooming business')
            && ! str_contains($blob, 'pest eradicator')
            && ! str_contains($blob, 'goated')) {
            return;
        }

        $multipliers['farming'] *= 1.12;
        $multipliers['garden'] *= 1.10;
        $activeBoosts[] = [
            'label' => 'Finnegan',
            'detail' => '+12% Garden crops, +10% visitors/compost (modeled)',
            'activities' => ['farming', 'garden'],
        ];
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyCole(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'cole')
            && ! str_contains($blob, 'mining fiesta')
            && ! str_contains($blob, 'prospection')
            && ! str_contains($blob, 'forge')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['mining'],
            1.15,
            'Cole',
            '+15% mining drops & gemstone sell value (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyMarina(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'marina')
            && ! str_contains($blob, 'fishing festival')
            && ! str_contains($blob, 'luck of the sea')
            && ! str_contains($blob, 'fishing xp')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['fishing'],
            1.12,
            'Marina',
            '+12% fishing & sea creature profit (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyPaul(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'paul')
            && ! str_contains($blob, 'benediction')
            && ! str_contains($blob, 'marauder')
            && ! str_contains($blob, 'ezpz')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['dungeons'],
            1.14,
            'Paul',
            '+14% dungeon run profit (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyAatrox(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'aatrox')
            && ! str_contains($blob, 'slayer xp')
            && ! str_contains($blob, 'pestilence')
            && ! str_contains($blob, 'pathfinder')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['slayer'],
            1.12,
            'Aatrox',
            '+12% slayer profit from drops & XP efficiency (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyDiaz(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'diaz')
            && ! str_contains($blob, 'stock exchange')
            && ! str_contains($blob, 'volume trading')
            && ! str_contains($blob, 'shopping spree')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['farming', 'garden', 'mining', 'foraging', 'fishing', 'zealots'],
            1.035,
            'Diaz',
            'Lower Bazaar tax → ~+3.5% net on insta-sell methods (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyDiana(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'diana')
            && ! str_contains($blob, 'mythological ritual')
            && ! str_contains($blob, 'pet xp')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['zealots'],
            1.08,
            'Diana',
            '+8% mythological / eye-related profit (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyDerpy(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'derpy')
            && ! str_contains($blob, 'catacombs buff')
            && ! str_contains($blob, 'wisdom')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['dungeons'],
            1.08,
            'Derpy',
            '+8% dungeon profit (Catacombs buff, modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyFoxy(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'foxy')
            && ! str_contains($blob, 'extra event')
            && ! str_contains($blob, 'sweet benevolence')) {
            return;
        }

        $this->bump(
            $multipliers,
            ['farming', 'fishing', 'mining'],
            1.04,
            'Foxy',
            '+4% event-tied methods during festivals (modeled)',
            $activeBoosts,
        );
    }

    /**
     * @param  array<string, float>  $multipliers
     * @param  array<int, array{label: string, detail: string, activities: list<string>}>  $activeBoosts
     */
    private function applyJerry(string $name, string $blob, array &$multipliers, array &$activeBoosts): void
    {
        if (! str_contains(strtolower($name), 'jerry')
            && ! str_contains($blob, 'perkpocalypse')
            && ! str_contains($blob, 'jerrypocalypse')) {
            return;
        }

        foreach (array_keys($multipliers) as $key) {
            $multipliers[$key] *= 1.05;
        }

        $activeBoosts[] = [
            'label' => 'Jerry',
            'detail' => '+5% all methods (Perkpocalypse chaos, modeled)',
            'activities' => array_keys($multipliers),
        ];
    }

    public function multiplierFor(string $activity, array $mayorBoosts): float
    {
        return (float) ($mayorBoosts['multipliers'][$activity] ?? 1.0);
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public function boostsForActivity(string $activity, array $mayorBoosts): array
    {
        $out = [];
        foreach ($mayorBoosts['active_boosts'] ?? [] as $boost) {
            if (! is_array($boost)) {
                continue;
            }
            $activities = $boost['activities'] ?? [];
            if (! in_array($activity, $activities, true)) {
                continue;
            }
            $mult = (float) ($mayorBoosts['multipliers'][$activity] ?? 1.0);
            if ($mult <= 1.001) {
                continue;
            }
            $out[] = [
                'label' => (string) ($boost['label'] ?? 'Mayor'),
                'detail' => (string) ($boost['detail'] ?? ''),
            ];
        }

        return $out;
    }

    public function formatMultiplierPercent(float $multiplier): string
    {
        if ($multiplier <= 1.0001) {
            return '—';
        }

        return '+'.round(($multiplier - 1.0) * 100, 1).'%';
    }
}
