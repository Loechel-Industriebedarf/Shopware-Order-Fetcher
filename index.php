<?php
class ApiClient
{
    const METHOD_GET = 'GET';
    const METHOD_PUT = 'PUT';
    const METHOD_POST = 'POST';
    const METHOD_DELETE = 'DELETE';
    protected $validMethods = [
        self::METHOD_GET,
        self::METHOD_PUT,
        self::METHOD_POST,
        self::METHOD_DELETE,
    ];
    protected $apiUrl;
    protected $cURL;

    public function __construct($apiUrl, $username, $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        //Initializes the cURL instance
        $this->cURL = curl_init();
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->cURL, CURLOPT_USERAGENT, 'Shopware ApiClient');
        curl_setopt($this->cURL, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($this->cURL, CURLOPT_USERPWD, $username . ':' . $apiKey);
        curl_setopt(
            $this->cURL,
            CURLOPT_HTTPHEADER,
            ['Content-Type: application/json; charset=utf-8']
        );
    }

    public function call($url, $method = self::METHOD_GET, $data = [], $params = [])
    {
        if (!in_array($method, $this->validMethods)) {
            throw new Exception('Invalid HTTP-Methode: ' . $method);
        }
        $queryString = '';
        if (!empty($params)) {
            $queryString = http_build_query($params);
        }
        $url = rtrim($url, '?') . '?';
        $url = $this->apiUrl . $url . $queryString;
        $dataString = json_encode($data);
        curl_setopt($this->cURL, CURLOPT_URL, $url);
        curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $dataString);
        $result = curl_exec($this->cURL);
        $httpCode = curl_getinfo($this->cURL, CURLINFO_HTTP_CODE);

        return $this->prepareResponse($result, $httpCode);
    }

    public function get($url, $params = [])
    {
        return $this->call($url, self::METHOD_GET, [], $params);
    }

    public function post($url, $data = [], $params = [])
    {
        return $this->call($url, self::METHOD_POST, $data, $params);
    }

    public function put($url, $data = [], $params = [])
    {
        return $this->call($url, self::METHOD_PUT, $data, $params);
    }

    public function delete($url, $params = [])
    {
        return $this->call($url, self::METHOD_DELETE, [], $params);
    }

    protected function prepareResponse($result, $httpCode)
    {
        echo "HTTP: $httpCode";
        if (null === $decodedResult = json_decode($result, true)) {
            $jsonErrors = [
                JSON_ERROR_NONE => 'No error occurred',
                JSON_ERROR_DEPTH => 'The maximum stack depth has been reached',
                JSON_ERROR_CTRL_CHAR => 'Control character issue, maybe wrong encoded',
                JSON_ERROR_SYNTAX => 'Syntaxerror',
            ];
            echo 'Could not decode json';
            echo 'json_last_error: ' . $jsonErrors[json_last_error()];
            echo '<br>Raw:<br>';
            echo '<pre>' . print_r($result, true) . '</pre>';

            return;
        }
        if (!isset($decodedResult['success'])) {
            echo 'Invalid Response';

            return;
        }
        if (!$decodedResult['success']) {
            echo 'No Success';
            echo '<p>' . $decodedResult['message'] . '</p>';
            if (array_key_exists('errors', $decodedResult) && is_array($decodedResult['errors'])) {
                echo '<p>' . join('</p><p>', $decodedResult['errors']) . '</p>';
            }

            return;
        }
        echo 'Success';
        if (isset($decodedResult['data'])) {
			//Show order array
            echo '<pre>' . print_r($decodedResult['data'], true) . '</pre>';
        }

        return $decodedResult;
    }
}

$client = new ApiClient(
    //URL of shopware REST server
    '',
    //Username
    '',
    //User's API-Key
    ''
);

$logfile = 'shopware.log';
$csvfile = '../shopwareOrder.csv';

//Get current date
date_default_timezone_set('Europe/Berlin');
$date = date('m/d/Y h:i:s a', time());

//The order number of the newest order
$orders = $client->get('orders', [
'limit' => 1,
'sort' => [ 
	['property' => 'number', 'direction' => 'DESC']
]
]);
$current_order = $orders["data"][0]["number"];

//Read last order from file or transmit an order id
if(isset($_GET["id"])){
	$last_order = $_GET["id"];
}
else{
	$last_order = file_get_contents('last_order.txt');
}

//Only do something, if the csv file is non existent. Don't overwrite files.
if(!file_exists($csvfile)){
	if($last_order < $current_order){
	//Count order up
	$last_order++;
	$call = "orders/".$last_order."?useNumberAsId=true";
	
	//Call for order details
	$order_details = $client->get($call);
	
	if(isset($order_details["data"])){
		$list = array ( );
		//Add headline to list
		array_push($list, array(
		"Bestellung", "Nettowert", 
		"Artikelnr", "Preis", "Anzahl",
		"Zahlungsart", "Versandkosten",
		"BillingFirma", "BillingStrasse", "BillingPLZ", "BillingOrt",
		"BillingLand", "BillingLKZ", "ShipingFirma", 
		"ShipingStrasse", "ShipingPLZ", "ShipingOrt",
		"ShipingLand", "ShipingLKZ", "Mail"
		));
		//Add content to list
		$order_data = $order_details["data"];
		$order_item = $order_details["data"]["details"];
		$order_payment = $order_details["data"]["payment"];
		$order_billing = $order_details["data"]["billing"];
		$order_shipping = $order_details["data"]["shipping"];
		/*
			Notes:
			Item-price is always net
		*/
		$billing_adress = trim($order_billing["company"]." ".$order_billing["firstName"]." ".$order_billing["lastName"]);
		$shipping_adress = trim($order_shipping["company"]." ".$order_shipping["firstName"]." ".$order_shipping["lastName"]);
		foreach($order_item as $item){
			array_push($list, array(
			$order_data["number"], $order_data['invoiceAmountNet'], 
			$item["articleNumber"], $item["price"], $item["quantity"],
			$order_payment["description"], $order_data["invoiceShippingNet"],
			$billing_adress, $order_billing["street"], $order_billing["zipCode"], $order_billing["city"],
			$order_billing["country"]["name"], $order_billing["country"]["iso"],
			$shipping_adress, $order_shipping["street"], $order_shipping["zipCode"], $order_shipping["city"],
			$order_shipping["country"]["name"], $order_shipping["country"]["iso"],
			$order_data["customer"]["email"]
			));
		}
		
		
		$fp = fopen($csvfile, 'w');

		for ($i = 0; $i < count($list); $i++) {
			fputcsv($fp, $list[$i], ';');
		}

		fclose($fp);
		
		
		$logmsg = "\r\n".$date." | Bestellung ".$order_data["number"]." von ".$shipping_adress." verarbeitet!";
		echo $logmsg;
		
		
		file_put_contents($logfile, $logmsg, FILE_APPEND | LOCK_EX);
	}
	
	//Write last order number to file
	$myfile = fopen("last_order.txt", "w") or die("Unable to open file!");
	fwrite($myfile, $last_order);
	fclose($myfile);
	}
	else{
		$logmsg = "\r\n".$date." | Keine neuen Bestellungen.";
		echo $logmsg;
		file_put_contents($logfile, $logmsg, FILE_APPEND | LOCK_EX);
	}
}
else{
	$logmsg = "\r\n".$date." | Vorhandene Bestellung wurde noch nicht verarbeitet!";
	echo $logmsg;
	file_put_contents($logfile, $logmsg, FILE_APPEND | LOCK_EX);
}
