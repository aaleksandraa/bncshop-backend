<?php

namespace App\Services\Eline;

class ElineShelfPrice
{
    /**
     * Cijena je polje mpc iz cjenovnika.
     * Ako artikal već ima tačan mpc nekog skladišta, taj iznos ostaje.
     * Inače se uzima mpc reda koji ima najveće stanje.
     *
     * @param  list<array{mpc: float, active?: bool, stanje?: float|null}>  $rows
     */
    public function resolve(array $rows, ?float $current = null): float
    {
        $pool = $this->rows($rows);

        if ($pool === []) {
            return 0.0;
        }

        $current = round((float) $current, 2);

        if ($current > 0) {
            foreach ($pool as $row) {
                if ($this->cents((float) $row['mpc']) === $this->cents($current)) {
                    return round((float) $row['mpc'], 2);
                }
            }
        }

        usort($pool, function (array $left, array $right): int {
            $stock = ((float) ($right['stanje'] ?? 0)) <=> ((float) ($left['stanje'] ?? 0));

            if ($stock !== 0) {
                return $stock;
            }

            return ((float) $right['mpc']) <=> ((float) $left['mpc']);
        });

        return round((float) $pool[0]['mpc'], 2);
    }

    /**
     * @param  list<array{mpc: float, active?: bool, stanje?: float|null}>  $rows
     * @return list<array{mpc: float, active?: bool, stanje?: float|null}>
     */
    private function rows(array $rows): array
    {
        $active = [];
        $all = [];

        foreach ($rows as $row) {
            $mpc = round((float) ($row['mpc'] ?? 0), 2);

            if ($mpc <= 0) {
                continue;
            }

            $row['mpc'] = $mpc;
            $all[] = $row;

            if ($row['active'] ?? true) {
                $active[] = $row;
            }
        }

        return $active !== [] ? $active : $all;
    }

    private function cents(float $value): int
    {
        return (int) round($value * 100);
    }
}
