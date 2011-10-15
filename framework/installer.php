<?php
    class YpfInstaller extends PackageInstaller {
        public function install($packagePath) {
            return true;
        }

        public function uninstall() {
            return true;
        }

        public function configureTo($package, $packagePath) {
            if (!($package instanceof ApplicationPackage))
            {
                Logger::log('ERROR', sprintf('Could not configure %s to %s because it is not an application', $this->package->getName(), $package->getName()));
                return false;
            }

            $appIndexFile = getFilePath($this->package->getPackageRoot(), 'new_app/www/index.php');
            $index = file_get_contents($appIndexFile);
            $index = str_replace('{%YPF_REAL_PATH}', $this->package->getPackageRoot(), $index);
            $result = file_put_contents($appIndexFile, $index);

            if ($package->getDeployMode()) {
                $url = parse_url($package->getDeployUrl());
                $htaccessFile = getFilePath($packagePath, 'www/.htaccess');
                $contents = file($htaccessFile);

                if (!isset ($url['path']))
                    $url['path'] = '';

                $fd = fopen($htaccessFile, 'w');
                foreach ($contents as $line => $text)
                    if (strpos($text, 'RewriteBase') !== false)
                        fputs ($fd, sprintf("  RewriteBase %s/\n", $url['path']));
                    else
                        fputs ($fd, $text."\n");

                fclose($fd);
            }
            return ($result !== false);
        }
    }
?>
