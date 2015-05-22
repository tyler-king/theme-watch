<?php

namespace TylerKing\ThemeHandler;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Touki\FTP\Model\Directory;
use Exception;

class CheckCommand extends BaseCommand {
  protected function configure() {
    $this
      ->setName('theme:check')
      ->setDescription('Check your configuration')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    # Start the FTP connection
    $this->startFTP();
    
    # All ok
    $output->writeln('<info>Configuration is OK</info>');
    $output->writeln(sprintf(">>> <comment>Local: %s</comment>", getcwd()));
    $output->writeln(sprintf(">>> <comment>Remote: %s</comment>", $this->config['ftp']['path']));
    
    $this->stopFTP();
  }
}
