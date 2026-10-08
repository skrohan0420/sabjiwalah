<?php

require __DIR__ . '/../../app/Services/CustomerSetup.php';

use App\Services\CustomerSetup;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

check(! CustomerSetup::complete(['role' => 'customer', 'name' => 'Customer 3314']), 'Placeholder customer must finish setup.');
check(! CustomerSetup::validName('customer 3314'), 'Placeholder cannot be re-saved.');
check(! CustomerSetup::validName(' '), 'Blank name cannot finish setup.');
check(CustomerSetup::complete(['role' => 'customer', 'name' => 'Rohan']), 'Completed customer should continue.');
check(CustomerSetup::complete(['role' => 'admin', 'name' => 'Customer']), 'Non-customer roles are unaffected.');
check(CustomerSetup::validName('রোহন'), 'Unicode names are accepted.');
check(! CustomerSetup::validPin(null), 'Setup requires a pin.');
check(! CustomerSetup::validPin(['latitude' => 91, 'longitude' => 88]), 'Invalid coordinates cannot finish setup.');
check(CustomerSetup::validPin(['latitude' => 22.86, 'longitude' => 88.37]), 'Valid doorstep pin is accepted.');
echo "Customer setup validation passed.\n";
