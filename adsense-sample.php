<?php
require_once 'templates/base.php';
session_start();

/************************************************
  ATTENTION: Change this path to point to your
  client library installation!
 ************************************************/
set_include_path('path/to/google-api-php-client/src' . PATH_SEPARATOR . get_include_path());

require_once 'Google/autoload.php';
require_once 'Google/Client.php';
require_once 'Google/Service/AdSense.php';
require_once 'templates/base.php';
require_once 'examples/CollateReportData.php';
require_once 'examples/FillMissingDatesInReport.php';
require_once 'examples/GenerateReport.php';
require_once 'examples/GenerateReportWithPaging.php';
require_once 'examples/GenerateSavedReport.php';
require_once 'examples/GetAccountTree.php';
require_once 'examples/GetAllAccounts.php';
require_once 'examples/GetAllAdClients.php';
require_once 'examples/GetAllAdUnits.php';
require_once 'examples/GetAllAdUnitsForCustomChannel.php';
require_once 'examples/GetAllAlerts.php';
require_once 'examples/GetAllCustomChannels.php';
require_once 'examples/GetAllCustomChannelsForAdUnit.php';
require_once 'examples/GetAllDimensions.php';
require_once 'examples/GetAllMetrics.php';
require_once 'examples/GetAllSavedAdStyles.php';
require_once 'examples/GetAllSavedReports.php';
require_once 'examples/GetAllUrlChannels.php';

// Autoload example classes.
function __autoload($class_name) {
  include 'examples/' . $class_name . '.php';
}

// Max results per page.
define('MAX_LIST_PAGE_SIZE', 50, true);
define('MAX_REPORT_PAGE_SIZE', 50, true);

// Configure token storage on disk.
// If you want to store refresh tokens in a local disk file, set this to true.
define('STORE_ON_DISK', false, true);
define('TOKEN_FILENAME', 'tokens.dat', true);

// Set up authentication.
$client = new Google_Client();
$client->addScope('https://www.googleapis.com/auth/adsense.readonly');
$client->setAccessType('offline');

// Be sure to replace the contents of client_secrets.json with your developer
// credentials.
$client->setAuthConfigFile('client_secrets.json');
$client->setDeveloperKey('Add_Developer_Key');
// Create service.
$service = new Google_Service_AdSense($client);
//echo "$service<br>";
// If we're logging out we just need to clear our local access token.
// Note that this only logs you out of the session. If STORE_ON_DISK is
// enabled and you want to remove stored data, delete the file.
if (isset($_REQUEST['logout'])) {
  unset($_SESSION['access_token']);
}

// If we have a code back from the OAuth 2.0 flow, we need to exchange that
// with the authenticate() function. We store the resultant access token
// bundle in the session (and disk, if enabled), and redirect to this page.
if (isset($_GET['code'])) {
  $client->authenticate($_GET['code']);
  // Note that "getAccessToken" actually retrieves both the access and refresh
  // tokens, assuming both are available.
  $_SESSION['access_token'] = $client->getAccessToken();
  if (STORE_ON_DISK) {
    file_put_contents(TOKEN_FILENAME, $_SESSION['access_token']);
  }
  $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
  exit;
}

// If we have an access token, we can make requests, else we generate an
// authentication URL.
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
  $client->setAccessToken($_SESSION['access_token']);
} else if (STORE_ON_DISK && file_exists(TOKEN_FILENAME) &&
      filesize(TOKEN_FILENAME) > 0) {
  // Note that "setAccessToken" actually sets both the access and refresh token,
  // assuming both were saved.
  $client->setAccessToken(file_get_contents(TOKEN_FILENAME));
  $_SESSION['access_token'] = $client->getAccessToken();
} else {
  // If we're doing disk storage, generate a URL that forces user approval.
  // This is the only way to guarantee we get back a refresh token.
  if (STORE_ON_DISK) {
    $client->setApprovalPrompt('force');
  }
  $authUrl = $client->createAuthUrl();
}

echo pageHeader('AdSense Management');

echo '<div><div class="request">';
if (isset($authUrl)) {
  echo '<a class="login" href="' . $authUrl . '">Connect Me!</a>';
} else {
  echo '<a class="logout" href="?logout">Logout</a>';
};
echo '</div>';

if ($client->getAccessToken()) {
  echo '<pre class="result">';
  // Now we're signed in, we can make our requests.
  makeRequests($service);  
  // Note that we re-store the access_token bundle, just in case anything
  // changed during the request - the main thing that might happen here is the
  // access token itself is refreshed if the application has offline access.
  $_SESSION['access_token'] = $client->getAccessToken();
  echo '</pre>';
}

echo '</div>';
echo pageFooter(__FILE__);


// Makes all the API requests.
function makeRequests($service) {
   print "\n";
  $accounts = GetAllAccounts::run($service, MAX_LIST_PAGE_SIZE);

  if (isset($accounts) && !empty($accounts)) {
    // Get an example account ID, so we can run the following sample.
    $exampleAccountId = $accounts[0]['id'];    
    GetAccountTree::run($service, $exampleAccountId);
    $adClients =
        GetAllAdClients::run($service, $exampleAccountId, MAX_LIST_PAGE_SIZE);

    if (isset($adClients) && !empty($adClients)) {
      // Get an ad client ID, so we can run the rest of the samples.
      $exampleAdClient = end($adClients);
      $exampleAdClientId = $exampleAdClient['id'];

      $adUnits = GetAllAdUnits::run($service, $exampleAccountId,
          $exampleAdClientId, MAX_LIST_PAGE_SIZE);
      if (isset($adUnits) && !empty($adUnits)) {
        // Get an example ad unit ID, so we can run the following sample.
        $exampleAdUnitId = $adUnits[0]['id'];
        GetAllCustomChannelsForAdUnit::run($service, $exampleAccountId,
          $exampleAdClientId, $exampleAdUnitId, MAX_LIST_PAGE_SIZE);
      } else {
        print 'No ad units found, unable to run dependant example.';
      }

      $customChannels = GetAllCustomChannels::run($service, $exampleAccountId,
          $exampleAdClientId, MAX_LIST_PAGE_SIZE);
      if (isset($customChannels) && !empty($customChannels)) {
        // Get an example ad unit ID, so we can run the following sample.
        $exampleCustomChannelId = $customChannels[0]['id'];
        GetAllAdUnitsForCustomChannel::run($service, $exampleAccountId,
          $exampleAdClientId, $exampleCustomChannelId, MAX_LIST_PAGE_SIZE);
      } else {
        print 'No custom channels found, unable to run dependant example.';
      }

      GetAllUrlChannels::run($service, $exampleAccountId, $exampleAdClientId,
          MAX_LIST_PAGE_SIZE);
      GenerateReport::run($service, $exampleAccountId, $exampleAdClientId);
      GenerateReportWithPaging::run($service, $exampleAccountId,
          $exampleAdClientId, MAX_REPORT_PAGE_SIZE);
      FillMissingDatesInReport::run($service, $exampleAccountId,
          $exampleAdClientId);
      CollateReportData::run($service, $exampleAccountId, $exampleAdClientId);
    } else {
      print 'No ad clients found, unable to run dependant examples.';
    }

    $savedReports = GetAllSavedReports::run($service, $exampleAccountId,
        MAX_LIST_PAGE_SIZE);
    if (isset($savedReports) && !empty($savedReports)) {
      
      getTodayEarning($service, $exampleAccountId);

      getYesterdayEarning($service, $exampleAccountId);

      getThisMonthEarning($service, $exampleAccountId);

      getLastMonthEarning($service, $exampleAccountId);
    } else {
      print 'No saved reports found, unable to run dependant example.';
    }

    GetAllSavedAdStyles::run($service, $exampleAccountId, MAX_LIST_PAGE_SIZE);
    GetAllAlerts::run($service, $exampleAccountId);
  }

}

function getTodayEarning($service, $exampleAccountId) {
  echo "<h1>Today's Earning </h1>";
  GenerateSavedReport::run($service, $exampleAccountId, 'Today_ID');
}

function getYesterdayEarning($service, $exampleAccountId) {
  echo "<h1>Yesterday's Earning </h1>";
  GenerateSavedReport::run($service, $exampleAccountId, 'Yesterday_ID');
}

function getThisMonthEarning($service, $exampleAccountId) {
  echo "<h1>This Month's Earning </h1>";
  GenerateSavedReport::run($service, $exampleAccountId, 'This_Month_ID');
}

function getLastMonthEarning($service, $exampleAccountId) {
  echo "<h1>Last Month's Earning </h1>";
  GenerateSavedReport::run($service, $exampleAccountId, 'Last_Month_ID');
}
?>