<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
foreach($ite as $file) {
    if ($file->isFile() && $file->getExtension() == 'php') {
        $c = file_get_contents($file->getPathname());
        if (substr($c, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
            echo $file->getPathname() . " has BOM\n";
            file_put_contents($file->getPathname(), substr($c, 3));
        }
    }
}
echo "Done.";
