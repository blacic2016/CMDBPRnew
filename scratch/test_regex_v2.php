<?php
$lines = [
    "+--iso(1)",
    "   |",
    "   +--org(3)",
    "            |  |",
    "            |  +--mib-2(1)",
    "            |     |",
    "            |     +--system(1)",
    "            |     |  |",
    "            |     |  +-- -R-- String    sysDescr(1)",
    "            |     |  +-- -R-- ObjID     sysObjectID(2)",
    "            |     |  +-- -RW- String    sysContact(4)"
];

$regex = '/(?:\+--\s*)?(?:(\-[RW\-]+\-)\s+)?(?:([A-Za-z0-9\-]{3,})\s+)?([A-Za-z0-9\-_]+)\(([0-9]+)\)/';
// Note: I added {3,} to type to avoid matching short things like 'is' as type, 
// though the \s+ should already prevent it for 'iso(1)'.

foreach ($lines as $line) {
    echo "Line: $line\n";
    $line_clean = str_replace('|', ' ', $line);
    if (preg_match($regex, trim($line_clean), $m)) {
        print_r($m);
    } else {
        echo "No match\n";
    }
    echo "-------------------\n";
}
