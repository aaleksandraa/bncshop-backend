<?php

namespace App\Services\Eline;

use App\Services\Pricing\PriceCalculator;

class ElineShelfPrice
{
    public function __construct(private readonly PriceCalculator $priceCalculator) {}

    /**
     * Polica cijena je MPC iz eLine cjenovnika.
     * Zaokružena cijena (675 spremljena kao 669) vraća se na tu višu MPC.
     * Cijena koja je već jednaka nekoj MPC ostaje, čak i kad drugo skladište ima višu.
     * Inače se upisuje najviša aktivna MPC.
     *
     * @param  list<array{mpc: float, active?: bool}>  $rows
     */
    public function resolve(array $rows, ?float $current): float
    {
        $current = round((float) $current, 2);
        $lift = $this->charmLift($rows, $current);

        if ($lift !== null) {
            return $lift;
        }

        $pool = $this->pool($rows);

        if ($pool === []) {
            return 0.0;
        }

        if ($current > 0 && $this->contains($pool, $current)) {
            return $current;
        }

        return max($pool);
    }

    /**
     * @param  list<array{mpc: float, active?: bool}>  $rows
     */
    public function charmLift(array $rows, ?float $current): ?float
    {
        $current = round((float) $current, 2);

        if ($current <= 100) {
            return null;
        }

        $lift = [];

        foreach ($this->everyMpc($rows) as $mpc) {
            if ($mpc <= $current) {
                continue;
            }

            $charmed = round($this->priceCalculator->applyOver100CharmPrice($mpc), 2);

            if ($this->cents($charmed) === $this->cents($current)) {
                $lift[] = $mpc;
            }
        }

        if ($lift === []) {
            return null;
        }

        return max($lift);
    }

    /**
     * @param  list<array{mpc: float, active?: bool}>  $rows
     * @return list<float>
     */
    private function pool(array $rows): array
    {
        $active = [];
        $all = [];

        foreach ($rows as $row) {
            $mpc = round((float) ($row['mpc'] ?? 0), 2);

            if ($mpc <= 0) {
                continue;
            }

            $all[] = $mpc;

            if ($row['active'] ?? true) {
                $active[] = $mpc;
            }
        }

        return $active !== [] ? $active : $all;
    }

    /**
     * @param  list<array{mpc: float, active?: bool}>  $rows
     * @return list<float>
     */
    private function everyMpc(array $rows): array
    {
        $mpcs = [];

        foreach ($rows as $row) {
            $mpc = round((float) ($row['mpc'] ?? 0), 2);

            if ($mpc > 0) {
                $mpcs[] = $mpc;
            }
        }

        return $mpcs;
    }

    /**
     * @param  list<float>  $mpcs
     */
    private function contains(array $mpcs, float $price): bool
    {
        $needle = $this->cents($price);

        foreach ($mpcs as $mpc) {
            if ($this->cents($mpc) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function cents(float $value): int
    {
        return (int) round($value * 100);
    }
}
