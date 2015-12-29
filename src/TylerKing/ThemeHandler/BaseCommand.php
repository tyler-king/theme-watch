<?php
namespace TylerKing\ThemeHandler;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
use Touki\FTP\Connection\Connection;
use Touki\FTP\Connection\SSLConnection;
use Touki\FTP\FTPFactory;
use Touki\FTP\Model\Directory;
use Exception;

class BaseCommand extends Command {
  protected $config,
            $connection,
            $ftp,
            $wrapper
  ;
  
  public function setupConfig() {
    # Get the config
    $config_path = getcwd().'/config.yml';
    if (! is_file($config_path)) {
      # Not the best way of handling, but oh well
      throw new Exception('Configuration file missing from this directory.');
    }
    
    try {
      $this->config = (new Parser)->parse(file_get_contents($config_path));
    } catch(ParseException $e) {
      throw new Exception(sprintf("Unable to parse the YAML string: %s", $e->getMessage()));
    }
  }
  
  protected function startConnection() {
    # Start a new connection with details given
    if (isset($this->config['ftp']['type']) && $this->config['ftp']['type'] == 'ssl') {
      # SSL connection
      $this->connection = new SSLConnection(
        $this->config['ftp']['host'],
        $this->config['ftp']['username'],
        $this->config['ftp']['password']
      );
    } else {
      # Standard connection
      $this->connection = new Connection(
        $this->config['ftp']['host'],
        $this->config['ftp']['username'],
        $this->config['ftp']['password']
      );
    }
    
    $this->connection->open();
  }
  
  protected function stopConnection() {
    $this->connection->close();
    
    $this->connection = null;
  }

  protected function startFTP() {
    if (null === $this->connection) {
      # No connection yet
      $this->startConnection();
    }
    
    # Build the FTP 
    $this->factory = new FTPFactory;
    $this->ftp     = $this->factory->build($this->connection);
    $this->wrapper = $this->factory->getWrapper();
    if (isset($this->config['ftp']['passive']) && $this->config['ftp']['passive'] == true) {
      $this->wrapper->pasv(true);
    }
    
    # Confirm remote path exists before changing directory remotely
    $exists = $this->ftp->directoryExists(new Directory($this->config['ftp']['path']));
    if (! $exists) {
      throw new Exception("Path \"{$this->config['ftp']['path']}\" is invalid");
    }
    
    # Move into the directory of the path provided
    $this->wrapper->chdir($this->config['ftp']['path']);
  }
  
  protected function stopFTP() {
    if ($this->connection) {
      $this->connection->close();
    }
    
    $this->ftp        = null;
    $this->wrapper    = null;
  }
  
  protected function getFileBase($file) {
    /*
     *  Makes both paths relative to eachother
     *  Assume CWD is cool-folder/
     *  Local: /Users/tyler/Development/cool-folder/css/main.css
     *  Remote: /cool-folder/css/main.css
     *  Base will change Local to match Remote
     */
    $base = substr($file, strpos($file, getcwd()) + strlen(getcwd()) + 1);

    # Fix for Windows environments (convert \ to /)
    return str_replace('\\', '/', $base);
  }
  
  protected function isIgnoredFile($file) {
    if (in_array(pathinfo($file, PATHINFO_BASENAME), ['.', '..']) || $file[0] == '.') {
      # Ignore dot "files"
      return true;
    }
    
    if (sizeof($this->config['theme']['ignore']) > 0) {
      foreach($this->config['theme']['ignore'] as $pattern) {
        if (fnmatch($pattern, $file)) {
          # Pattern matches, let the code know we need to ignore this file
          return true;
        }
      }
    }
    
    # No matches found, do not ignore
    return false;
  }
}