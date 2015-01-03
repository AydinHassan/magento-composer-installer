<?php

/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento\Command;

use MagentoHackathon\Composer\Magento\Deploy\Manager\Entry;
use MagentoHackathon\Composer\Magento\DeployManager;
use MagentoHackathon\Composer\Magento\Event\EventManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Downloader\VcsDownloader;
use MagentoHackathon\Composer\Magento\Installer;

/**
 * @author Tiago Ribeiro <tiago.ribeiro@seegno.com>
 * @author Rui Marinho <rui.marinho@seegno.com>
 */
class ComposerDeployCommand extends \Composer\Command\Command
{
    protected function configure()
    {
        $this
            ->setName('magento-module-deploy')
            ->setDescription('Deploy all Magento modules loaded via composer.json')
            ->setDefinition(array())
            ->setHelp('This command deploys all magento Modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // init repos
        $composer = $this->getComposer();
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();

        $dm = $composer->getDownloadManager();
        $im = $composer->getInstallationManager();

        /**
         * @var $moduleInstaller \MagentoHackathon\Composer\Magento\Installer\ModuleInstaller
         */
        $moduleInstaller = $im->getInstaller("magento-module");

        $eventManager   = new EventManager;
        $deployManager = new DeployManager($eventManager);

        $io = $this->getIo();
        if ($io->isDebug()) {
            $eventManager->listen('pre-package-deploy', function(PackageDeployEvent $event) use ($io) {
                $io->write('Start magento deploy for ' . $event->getDeployEntry()->getPackageName());
            });
        }

        $extra          = $composer->getPackage()->getExtra();
        $sortPriority   = array();
        if (isset($extra['magento-deploy-sort-priority'])) {
            $sortPriority = $extra['magento-deploy-sort-priority'];
        }
        $deployManager->setSortPriority($sortPriority);



        $moduleInstaller->setDeployManager($deployManager);
        

        foreach ($installedRepo->getPackages() as $package) {
            if ($input->getOption('verbose')) {
                $output->writeln($package->getName());
                $output->writeln($package->getType());
            }

            if ($package->getType() != "magento-module") {
                continue;
            }
            if ($input->getOption('verbose')) {
                $output->writeln("package {$package->getName()} recognized");
            }

            $strategy = $moduleInstaller->getDeployStrategy($package);
            if ($input->getOption('verbose')) {
                $output->writeln("used " . get_class($strategy) . " as deploy strategy");
            }
            $strategy->setMappings($moduleInstaller->getParser($package)->getMappings());

            $deployManagerEntry = new Entry();
            $deployManagerEntry->setPackageName($package->getName());
            $deployManagerEntry->setDeployStrategy($strategy);
            $deployManager->addPackage($deployManagerEntry);
            
        }

        $deployManager->doDeploy();

        return;
    }
}
