<?php
$key = 'vmware.vm.vfs.fs.size[{$VMWARE.URL},{$VMWARE.VM.UUID},/,total]';
$regex = '/vmware\.vm\.vfs\.fs\.size\[.*?,.*?,(?:["\']?)([^"\'\]]+)(?:["\']?)(?:,(.*))?\]/';
if (preg_match($regex, $key, $m)) {
    echo "Match 1: mount=" . $m[1] . " mode=" . ($m[2] ?? 'N/A') . "\n";
} else {
    echo "NO Match 1\n";
}

$key2 = 'vmware.vm.vfs.fs.size[{$VMWARE.URL},/,total]';
if (preg_match($regex, $key2, $m)) {
    echo "Match 2: mount=" . $m[1] . " mode=" . ($m[2] ?? 'N/A') . "\n";
} else {
    echo "NO Match 2\n";
}

$name = 'Mounted filesystem discovery: VMware: Total disk space on /';
if (preg_match('/discovery:\s+.*:\s+(.*)$/i', $name, $m)) {
     echo "Fallback: m1=" . $m[1] . " mount=" . trim(explode(':', $m[1])[0]) . "\n";
}
