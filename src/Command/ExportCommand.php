<?php

namespace Acquia\Search\Export\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Acquia\Network\AcquiaNetworkClient;
use Acquia\Search\AcquiaSearchService;
use Acquia\Common\AcquiaServiceManager;


class ExportCommand extends Command {
  protected function configure() {
    $this
      ->setName('export')
      ->setDescription('Export one or all Acquia Search Indexes from a certain subscription.')
      ->addOption(
        'index',
        'i',
        InputOption::VALUE_OPTIONAL,
        'The full name of the index to be checked. Eg.: ABCD-12345.'
      )
      ->addOption(
        'path',
        'p',
        InputOption::VALUE_REQUIRED,
        'the full path to where the export should be saved to. This path should exist.',
        '/tmp/search_export'
      )
    ;

  }

  protected function execute(InputInterface $input, OutputInterface $output) {

    $index_given = $input->getOption('index');
    $path = $input->getOption('path');



    $verbosityLevelMap = array(
      'notice' => OutputInterface::VERBOSITY_NORMAL,
      'info' => OutputInterface::VERBOSITY_NORMAL,
    );
    $logger = new ConsoleLogger($output, $verbosityLevelMap);

    // Get the Acquia Network Subscription
    $subscription = $this->getSubscription($output);

    $logger->info('Checking if the given subscription has Acquia Search indexes...');
    if (!empty($subscription['heartbeat_data']['search_cores']) && is_array($subscription['heartbeat_data']['search_cores'])) {
      $search_cores = $subscription['heartbeat_data']['search_cores'];
      $count = count($subscription['heartbeat_data']['search_cores']);
      $logger->info('Found ' . $count . ' Acquia Search indexes.');
    }
    else {
      $logger->error('No Search Cores found for given subscription');
      exit();
    }

    // Loop through each cores.
    foreach ($search_cores as $search_core) {
      $search_core_identifier = $search_core['core_id'];
      // If a search index argument was given, only fetch those documents
      if (isset($index_given) && $search_core_identifier !== $index_given) {
        continue;
      }
      // A subscription can have multiple indexes. The Acquia Search service builder
      // generates credentials and clients for all of the subscription's indexes.
      $search = AcquiaSearchService::factory($subscription);

      /** @var \PSolr\Client\SolrClient $index */
      $index = $search->get($search_core_identifier);

      // Retrieve the amount of documents in the index
      $index->get('admin/ping?wt=json')->send()->json();
      $response = $index->get('/admin/luke?wt=json&numTerms=0')->send()->json();

      if (!isset($response['index']['numDocs'])) {
        $logger->error('Index ' . $search_core_identifier . 'did not have any indexed items');
        exit();
      }

      if (!file_exists($path)) {
        $logger->error($path . ' does not exists. Please create the path before exporting solr documents.');
        exit(1);
      }

      // Make the directory
      if (!file_exists($path .'/' . $search_core_identifier)) {
        mkdir($path .'/' . $search_core_identifier, 0700);
      }

      // Get the number of documents in this index
      $numDocuments = $response['index']['numDocs'];

      $processed_count = 0;
      $offset = 0;

      $logger->info('Exporting all documents for index ' . $search_core_identifier . '.');
      // Retrieve all the documents from the query, 200 at a time.
      while ($processed_count < $numDocuments) {
        $response = $index->get('select/?qt=standard&q=*:*&start=' . $offset . '&rows=200&sort=id%20asc&fl=*&wt=json')
          ->send()
          ->json();
        $documents = $response['response']['docs'];
        $processed_count += count($documents);
        $offset += count($documents);
        $logger->info('Found ' . count($documents) . ' documents. Exporting...');
        // Export all documents to an xml file
        foreach ($documents as $document) {
          $filename = $this->sanitize_file_name($document['id']) . '.xml';
          $xmlDocument = $this->documentToXml($document);
          file_put_contents($path . '/' . $search_core_identifier . '/' . $filename, $xmlDocument) || die("can't open file");
        }
        $logger->info('Exported ' . count($documents) . ' documents. Checking for more documents');
      }
      $logger->info('Exported ' . $processed_count . ' documents. Finished export for ' . $search_core_identifier . '.');

    }

  }

  /**
   * Create an XML fragment from a ApacheSolrDocument instance appropriate for use inside a Solr add call
   *
   * @return string
   */
  protected function documentToXml($document, $discard = array('spell')) {
    $xml = '<doc>';

    foreach ($document as $key => $value) {
      if (!in_array($key, $discard)) {
        $key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        if (is_array($value)) {
          foreach ($value as $id => $multivalue) {
            $xml .= '<field name="' . $key . '"';
            $xml .= '>' . htmlspecialchars($multivalue, ENT_NOQUOTES, 'UTF-8') . '</field>' . PHP_EOL;
          }
        }
        else {
          $xml .= '<field name="' . $key . '"';
          $xml .= '>' . htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8') . '</field>' . PHP_EOL;
        }
      }
    }

    $xml .= '</doc>';

    // Remove any control characters to avoid Solr XML parser exception
    return $this->stripCtrlChars($xml);
  }

  /**
   * Sanitizes a filename replacing whitespace with dashes
   *
   */
  protected function sanitize_file_name($filename) {
    return preg_replace(array('/[^\w_\.\-]/', '/\.[\.]+/'), array(
        '_',
        '.'
      ), $filename);
  }

  /**
   * Replace control (non-printable) characters from string that are invalid to Solr's XML parser with a space.
   *
   * @param string $string
   * @return string
   */
  protected function stripCtrlChars($string) {
    // See:  http://w3.org/International/questions/qa-forms-utf-8.html
    // Printable utf-8 does not include any of these chars below x7F
    return preg_replace('@[\x00-\x08\x0B\x0C\x0E-\x1F]@', ' ', $string);

  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return \Acquia\Network\AcquiaNetworkClient
   */
  public function getSubscription(OutputInterface $output)
  {

    $services = new AcquiaServiceManager(array(
      'conf_dir' => $_SERVER['HOME'] . '/.Acquia/auth',
    ));

    $network = $services->getClient('network', 'network');
    if (!$network) {
      $config = $this->promptIdentity($output);
      $network = AcquiaNetworkClient::factory($config);
      $services->setClient('network', 'network', $network);
      $services->saveServiceGroup('network');
    }
    // Get the subscription
    $subscription = $network->checkSubscription();

    return $subscription;
  }

  /**
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return array
   */
  public function promptIdentity(OutputInterface $output)
  {
    /** @var \Symfony\Component\Console\Helper\DialogHelper $dialog */
    $dialog = $this->getHelperSet()->get('dialog');
    return array(
      'network_id' => $dialog->ask($output, 'Acquia Network ID: '),
      'network_key' => $dialog->askHiddenResponse($output, 'Acquia Network Key: '),
    );
  }
}