<?php
// Cross-platform source lint. Blade templates are exercised by feature tests.
$root=dirname(__DIR__);
$files=[$root.'/artisan'];
foreach(['app','bootstrap','config','database','public','routes','tests','tools'] as $directory){
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $file){
        if($file->isFile()&&$file->getExtension()==='php'&&!str_ends_with($file->getFilename(),'.blade.php')){
            $files[]=$file->getPathname();
        }
    }
}
sort($files);
$failed=false;
foreach($files as $file){
    passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file),$status);
    if($status!==0)$failed=true;
}
exit($failed?1:0);
