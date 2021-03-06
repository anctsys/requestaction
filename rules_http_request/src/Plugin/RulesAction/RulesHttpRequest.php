<?php

namespace Drupal\rules_http_request\Plugin\RulesAction;

use Drupal\rules\Core\RulesActionBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides "Rules API Post" rules action.
 *
 * @RulesAction(
 *   id = "RulesHttpRequest",
 *   label = @Translation("Rules HTTP Request"),
 *   category = @Translation("Data"),
 *   context = {
 *     "url" = @ContextDefinition("string",
 *       label = @Translation("URL"),
 *       description = @Translation("The Url address where to post, get and delete request send. <br><b>Example:</b> https://example.com/node?_format=hal_json "),
 *       multiple = TRUE,
 *       required = TRUE,
 *     ),
 *     "method" = @ContextDefinition("string",
 *       label = @Translation("Method"),
 *       description = @Translation("The HTTP request methods like'HEAD','POST','PUT','DELETE','TRACE','OPTIONS','CONNECT','PATCH' etc."),
 *       required = TRUE,
 *      ),
 *     "headers" = @ContextDefinition("string",
 *       label = @Translation("Headers"),
 *       description = @Translation("Request headers to send as 'name: value' pairs, one per line (e.g., Accept: text/plain). See <a href='https://www.wikipedia.org/wiki/List_of_HTTP_header_fields'>wikipedia.org/wiki/List_of_HTTP_header_fields</a> for more information."),
 *       multiple = TRUE,
 *       required = FALSE,
 *      ),
 *     "apiuser" = @ContextDefinition("string",
 *       label = @Translation("API User Name"),
 *       description = @Translation("Username for API Access"),
 *       required = FALSE,
 *      ),
 *     "apipass" = @ContextDefinition("string",
 *       label = @Translation("API User Password"),
 *       description = @Translation("Password for API Access"),
 *       required = FALSE,
 *      ),
 *     "apitoken" = @ContextDefinition("string",
 *       label = @Translation("API Session Token"),
 *       description = @Translation("Session Token for API Access"),
 *       required = FALSE,
 *      ),
 *     "post_title" = @ContextDefinition("string",
 *       label = @Translation("Post Title"),
 *       description = @Translation("A pass through for our content titles."),
 *       required = FALSE,
 *      ),
 *     "extra_data" = @ContextDefinition("string",
 *       label = @Translation("Extra data to send to api"),
 *       description = @Translation("A pass through for our content extra data field."),
 *       required = FALSE,
 *      ),
 *     "node_body" = @ContextDefinition("entity:node",
 *       label = @Translation("Node Content"),
 *       description = @Translation("Pass node content entity"),
 *       required = FALSE,
 *      ),
 *     "max_redirects" = @ContextDefinition("integer",
 *       label = @Translation("Max Redirect"),
 *       description = @Translation("How many times a redirect may be followed."),
 *       default_value = 3,
 *       required = FALSE,
 *       assignment_restriction = "input",
 *     ),
 *     "timeout" = @ContextDefinition("float",
 *       label = @Translation("Timeout"),
 *       description = @Translation("The maximum number of seconds the request may take.."),
 *       default_value = 30,
 *       required = FALSE,
 *     ),
 *   },
 *   provides = {
 *     "http_response" = @ContextDefinition("string",
 *       label = @Translation("HTTP data")
 *     )
 *   }
 * )
 *
 */
class RulesHttpRequest extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The logger for the rules channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a httpClient object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param GuzzleHttp\ClientInterface $http_client
   *   The guzzle http client instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger_factory->get('rules_http_request');
    $this->http_client = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('http_client')
    );
  }

  /**
   * Set up form variables
   *
   * @param string[] $url
   *   Url addresses HTTP request.
   * @param string[] $methode
   *   (optional) The Node Type for API call
   * @param string[] $apiuser
   *   (optional) The User Name for API call
   * @param string[] $apipass
   *   (optional) The User Passord for API call
   * @param string[] $apitoken
   *   (optional) The Session Token for API call
   * @param string[] $post_title
   *   (optional) A passthrough for content titles.
   * @param string[] $extra_data
   *   (optional) A passthrough for content titles.
   * @param  $node_body
   *   (optional) A passthrough the node content.
   */
//TODO Rajouter la gestion des parametres quand on en aura besoin : The request body, formatter as 'param=value&param=value&...'
//TODO nodetype à remplacer methodetype (post etc)
//TODO Pour configurer le client http : https://symfony.com/doc/current/http_client.html
//TODO Il faut tester si les valeurs passées en parametre dans le formulaire sont non nulles. Sinon Crash

protected function doExecute(array $url,$method,$headers, $apiuser, $apipass, $apitoken, $post_title, $extra_data ,$node_body,$max_redirects,$timeout) {
// Debug message
drupal_set_message(t("Activating Rules API POST ..."), 'status');

//Serialisation de l'entité => Conversion en une chaine de caractere json
/** @var \Symfony\Component\Serializer\Encoder\DecoderInterface $serializer */
$serializer = \Drupal::service('serializer');
//TODO Faire un if node_body
$data = $serializer->serialize($node_body, 'json', ['plugin_id' => 'entity']);

//node_body est un objet php (phpobject)
//Transformation de l'objet PHP node entity en une array (désérialisation puis extraction des champs de l'objet php)
$xdata=json_decode($data);
$node_body_array=get_object_vars($xdata);



//Gestion des messages
$messenger = \Drupal::messenger();

//Extraction de la valeur target_uuid de node_body_array
try {
  $extract_user_uuid_rev=$node_body_array["revision_uid"][0];//C'est un objet
  $extract_user_uuid_rev_json_object=$extract_user_uuid_rev->target_uuid;
}catch(Exception $e) {
    \Drupal::logger('my_module')->error($e);
    $messenger->addMessage("Erreur dans le format du node", $messenger::TYPE_ERROR);
}

$utilisateur_machine="8a2b39d0-2642-4e8c-8774-88e6fe87e874";
//Celapermet de compenser que l'on ne puisse pas utiliser les conditifons de rules sur le roôle de l'utilisateur
if (strcmp($extract_user_uuid_rev_json_object, $utilisateur_machine) !== 0) {
    //Les utilisateur sont <> donc on peut lancer le processus


//$messenger->addMessage($extract_user_uuid_rev, $messenger::TYPE_WARNING);
//$messenger->addMessage('Start Rules', $messenger::TYPE_WARNING);

//PREPARATION DU HEADER à partir des données du champ RULES -  Entêtes
//Extraction des données du champs header pour ajouter à l'array option,dans rules un item par ligne voir ci-dessous
//Content-Type:application/json
//Accept:application/json
//X-CSRF-Token:QJiVCdcBzojqF9L-VG6vvQR9-8Wxa292fB7Z
//TODO Pour configurer le client http : https://symfony.com/doc/current/http_client.html
if (is_array($headers)) {
  foreach ($headers as $header) {
    if (!empty($header) && strpos($header, ':') !== FALSE) {
      list($name, $value) = explode(':', $header, 2);
      if (!empty($name)) {
        $options['headers'][$name] = ltrim($value);
      }
    }
  }
}



//For test only
//$messenger->addMessage(implode ( $options , "#" ), $messenger::TYPE_WARNING);

//PREPARATION DU BODY
//Encodage json du BODY
$serialized_entity = json_encode([
  //Titre du contenu ou du node au choix
  'title' => [['value' => $post_title]],
  //Donnes supplémentaires
  'extra_data' => [['value' => $extra_data, 'format' => 'full_html']],
  //Contenu du Node
  //'jsonnode' => [['nodevalue' => $data]],//ORIGINAL -->C'est à cause du nouvel encodage en json que l'on a des problème de format avec des caractère d'échappement
  'jsonnode' => $node_body_array, //Marche nickel
  //'test1'=>$extract_user_uuid_rev,
  //'test2'=>$extract_user_uuid_rev_json_object,
]);

$client = \Drupal::httpClient();
$url =$url[0];
//$method = 'POST';
/*
$options = [
  'auth' => [
    $apiuser,
    $apipass
  ],
'timeout' => '2',
'body' => $serialized_entity,
//'node' => $data
'headers' => [
'Content-Type' => 'application/hal+json',
'Accept' => 'application/hal+json',
'X-CSRF-Token' => $apitoken
    ],
];
*/

//Non utilisé pour le moment

$options['auth'] = [
  $apiuser[0],
  $apipass[0],
];


// Timeout.
$options['timeout'] = empty($timeOut) ? 30 : $timeOut;

// Max redirects.
$options['max_redirects'] = empty($maxRedirect) ? 3 : $maxRedirect;

//Formalisation de la matrice de diffusion
if(!empty($serialized_entity)){
  $options['body']= $serialized_entity;
}
//Champ TOKEN de rules non nul alors modifier le champs header[token] de la matrice de diffusion
if(!empty($apitoken)){
  $options['headers']['X-CSRF-Token'] = $apitoken;
}


try {
  $response = $client->request($method, $url, $options);
  $code = $response->getStatusCode();
  if ($code == 200) {
    $body = $response->getBody()->getContents();
    $messenger->addMessage($body, $messenger::TYPE_WARNING);
    return $body;
  }else{
    //Autres code
    $messenger->addMessage("Code : ".$code, $messenger::TYPE_ERROR);

  }
}
catch (RequestException $e) {
  \Drupal::logger('rules_http_request')->error($e);
  //watchdog_exception('rules_http_request', $e); //Drupal 7
  }
 }

}//Fin du if de comparaison des utilisateur machine et éditeur
}
