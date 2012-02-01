<?php
    class YpfInstaller extends YPIPackageInstaller {

        public function install($packagePath) {
            $version = implode('.', $this->package->getVersion());

            //Install ypf command
            $destFileName = getFileName(BIN_PATH, 'ypf');

            $destFileNameVersion =  $destFileName.'-'.$version;

            $srcFileName = getFileName($packagePath, 'bin/ypf');

            if (file_exists($destFileName))
                @unlink($destFileName);

            $result = symlink($srcFileName, $destFileName) &&
                      symlink($srcFileName, $destFileNameVersion) &&
                      chmod($srcFileName, 0555);
            if ($result)
                YPILogger::log ('INFO', sprintf ('YPFramework version %s: installed ypf command', $version));
            else
                YPILogger::log ('ERROR', sprintf ('YPFramework version %s: could not install ypf command', $version));

            return $result;
        }

        public function uninstall() {
            $version = implode('.', $this->package->getVersion());

            $destFileName = getFileName(BIN_PATH, 'ypf-'.$version);
            if (!file_exists($destFileName))
                $destFileName = getFileName(BIN_PATH, 'ypf');

            $result = unlink($destFileName);
            if ($result)
                YPILogger::log ('INFO', sprintf ('YPFramework version %s: uninstalled ypf command', $version));
            else
                YPILogger::log ('ERROR', sprintf ('YPFramework version %s: could not uninstall ypf command', $version));

            return $result;
        }

        public function configureTo($package, $packagePath) {

        }
    }
?>
