<?php
    class YpfInstaller extends YPIPackageInstaller {

        public function install($packagePath) {
            $version = implode('.', $this->package->getVersion());

            //Install ypf command
            $destFileName = getFileName(BIN_PATH, 'ypf');

            $destFileNameVersion =  $destFileName.'-'.$version;

            $srcFileName = getFileName($packagePath, 'bin/ypf');

            if (file_exists($destFileName))
                @rename ($destFileName, $destFileNameVersion.'-prev');


            $result =   symlink($srcFileName, $destFileName) &&
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
            $result = true;

            $destFileName = getFileName(BIN_PATH, 'ypf');
            if (file_exists($destFileName))
                $result = unlink($destFileName);

            $destFileName = getFileName(BIN_PATH, 'ypf-'.$version);
            if (file_exists($destFileName))
                $result = $result && unlink($destFileName);

            $destFileName .= '-prev';
            if (file_exists($destFileName))
                $result = $result && rename ($destFileName, getFileName(BIN_PATH, 'ypf'));

            if ($result)
                YPILogger::log ('INFO', sprintf ('YPFramework version %s: uninstalled ypf command', $version));
            else
                YPILogger::log ('ERROR', sprintf ('YPFramework version %s: could not uninstall ypf command', $version));

            return $result;
        }

        public function configureTo($package, $packagePath) {
            return true;
        }
    }
?>
