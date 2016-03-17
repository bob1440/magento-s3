<?php
require_once './abstract.php';

class Arkade_S3_Shell_Export extends Mage_Shell_Abstract
{
    protected function _validate()
    {
        if ($this->getArg('dry-run') && $this->getArg('force')) {
            echo "You can't use --dry-run and --force at the same time!\n";
        }

        parent::_validate();
    }

    public function run()
    {
        if ($this->getArg('dry-run') || $this->getArg('force')) {
            /** @var Mage_Core_Helper_File_Storage $helper */
            $helper = Mage::helper('core/file_storage');

            // Stop S3 from syncing to itself
            if (Arkade_S3_Model_Core_File_Storage::STORAGE_MEDIA_S3 == $helper->getCurrentStorageCode()) {
                echo "You are already using S3 as your media file storage backend!\n";

                return $this;
            }

            /** @var Mage_Core_Model_File_Storage_File|Mage_Core_Model_File_Storage_Database $sourceModel */
            $sourceModel = $helper->getStorageModel();

            /** @var Arkade_S3_Model_Core_File_Storage_S3 $destinationModel */
            $destinationModel = $helper->getStorageModel(Arkade_S3_Model_Core_File_Storage::STORAGE_MEDIA_S3);

            $offset = 0;
            while (($files = $sourceModel->exportFiles($offset, 1)) !== false) {
                foreach ($files as $file) {
                    echo sprintf("Uploading %s to S3.\n", $file['directory'] . '/' . $file['filename']);
                }
                if ($this->getArg('force')) {
                    $destinationModel->importFiles($files);
                }
                $offset += count($files);
            }
            unset($files);

            // Update configuration to tell Magento that we are now using S3
            if ($this->getArg('force')) {
                echo "Updating configuration to use S3.\n";

                Mage::getConfig()->saveConfig('system/media_storage_configuration/media_storage', 2);
                Mage::app()->getConfig()->reinit();
            }
        } else {
            echo $this->usageHelp();
        }

        return $this;
    }

    public function usageHelp()
    {
        return <<<USAGE
\033[1mDESCRIPTION\033[0m
    This script allows the developer to export all media files from the current
    storage backend, i.e. file system or database, to S3 via the command line.

\033[1mSYNOPSIS\033[0m
    php s3_export.php [--force]
                      [--dry-run]
                      [-h] [--help]

\033[1mOPTIONS\033[0m
    --force
        This parameter will legit upload your media files to S3.

        \033[1mNOTE:\033[0m Please make sure to back up your media files before you run this!
        You never know what might happen!

    --dry-run
        This parameter will allow developers to simulate exporting media files
        to S3 without actually uploading anything!


USAGE;
    }
}

$shell = new Arkade_S3_Shell_Export();
$shell->run();
