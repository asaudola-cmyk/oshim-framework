<?php
declare(strict_types=1);

function add(int $a, int $b): int {
    return $a + $b;
}

function main(): int {
    int $result = add(500, 500);
    echo "Hello from OSHIM Native Binary! The result is: ";
    echo $result;
    return 0;
}