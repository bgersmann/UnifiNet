<?php

declare( strict_types = 1 );

class UnifiGateway extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString( 'ServerAddress', '192.168.178.1' );
        $this->RegisterPropertyString( 'APIKey', '' );
        $this->RegisterPropertyString( 'Site', 'default' );
        $this->RegisterPropertyBoolean('applicationVersion', 0);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $vpos = 100;
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $this->MaintainVariable( 'applicationVersion', $this->Translate( 'Application Version' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("applicationVersion") );
        if (empty($APIKey))
		{
		    // instance inactive
			$this->SetStatus( 104 );
		} else {
		    // instance active
			$this->SetStatus( 102 );
            $this->SetSummary($this->ReadPropertyString("ServerAddress"));        
		}
    }

    public function ForwardData( $JSONString )
    {
        $data = json_decode( $JSONString );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        if (empty($APIKey))
		{
		    // instance inactive
			$this->SetStatus( 104 );
		} else {
		    // instance active
			$this->SetStatus( 102 );
		}

		if (isset($data->Api)) {
			switch ($data->Api) {               
                case "getSiteWans":
                    if (isset($data->Param1)) {
                        $array = $this->getSiteWans(IPS_GetProperty( $data->InstanceID, 'SiteID' ));
                        return serialize($array);
                    }
                    return serialize([]);
                    break;
                case "getWifis":
                    if (isset($data->Param1)) {
                        $array = $this->getWifis();
                        return serialize($array);
                    }
                    return serialize([]);
                    break;
                case "getWifiDetails":
                    if (!$this->networkVersionCheck()) {
                        return serialize(['code'=> 600]);
                    }
                    $array = $this->getWifiDetails(IPS_GetProperty( $data->InstanceID, 'ID' ));
                    return serialize($array);                    
                    break;
                case "getNetworks":
                    if (!$this->networkVersionCheck()) {
                        return serialize(['code'=> 600]);
                    }
                    if (isset($data->Param1)) {
                        $array = $this->getNetworks();
                        return serialize($array);
                    }
                    return serialize([]);
                    break;
                case "getNetworkDetails":
                    $array = $this->getNetworkDetails(IPS_GetProperty( $data->InstanceID, 'ID' ));
                    return serialize($array);                    
                    break;
                case "updateNetworkSettings":
                    $jsonString = $this->updateNetworkSettings(IPS_GetProperty( $data->InstanceID, 'ID' ),$data->Param1);
                    return serialize($jsonString);
                    break;                     
                case "updateWifiSettings":
                    $jsonString = $this->updateWifiSettings(IPS_GetProperty( $data->InstanceID, 'ID' ),$data->Param1);
                    return serialize($jsonString);
                    break;                  
				case "getClients":
					$array = $this->getClients();
					return serialize($array);
					break;
                case "getClientData":
					$jsonString = $this->getClientData(IPS_GetProperty( $data->InstanceID, 'ID' ));
					return serialize($jsonString);
					break;
                case "getDevices":
					$array = $this->getDevices();
					return serialize($array);
					break;
                case "getDeviceData":
					$jsonString = $this->getDeviceData(IPS_GetProperty( $data->InstanceID, 'ID' ));
					return serialize($jsonString);
					break;
                case "getDevicesConfig":
                    $jsonString = $this->getDevicesConfig();
					return serialize($jsonString);
					break;
                case "getDeviceStats":                    
                    $jsonString = $this->getDeviceStats(IPS_GetProperty( $data->InstanceID, 'ID' ));
                    return serialize($jsonString);

                    break;
                case "getDeviceName":
                    $this->SendDebug("UnifiGW", json_encode($data), 0);
                    if (isset($data->Param1)){
                        $jsonString = $this->getDeviceName($data->Param1);
                        return serialize($jsonString);  
                    } 
                    return serialize(false);
                    break;
                case "setPortCycle":
                    if (isset($data->Param1)){
                        $jsonString = $this->setPortCycle(IPS_GetProperty( $data->InstanceID, 'ID' ),intval($data->Param1));
                        return serialize($jsonString);
                    }
                    break;
                case "setRestartDevice":
                    $jsonString = $this->setRestartDevice(IPS_GetProperty( $data->InstanceID, 'ID' ));
                    return serialize($jsonString);
                    break;
                case "getSites":
					$array = $this->getSites();
					return serialize($array);
					break;

			}
			
		}
    }

    public function Send( int $id,string $Api, string $Text )
    {
        $this->SendDataToChildren( json_encode( [ 'DataID' => '{6E3E09BC-4C83-0ABF-4C97-7E7B8C70A64E}', 
                                                'id' =>  $id,
                                                'Api'=> $Api,
                                                'data'=> $Text ] ) );		
    }

    public function GetConfigurationForm() {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $site = $this->ReadPropertyString( 'Site' );
        if ( !empty( $APIKey ) && $this->GetStatus() === 102 ) {
            $arrayOptions = $this->getSites();
        } else {
            $arrayOptions[] = array( 'caption' => 'default', 'value' => 'default' );
        }
        $arrayStatus = array();

        $arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );
        $arrayStatus[] = array( 'code' => 201, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Fehler Datenabfrage' );
        $arrayStatus[] = array( 'code' => 400, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Bad Request' );
        $arrayStatus[] = array( 'code' => 401, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Unauthorized' );
        $arrayStatus[] = array( 'code' => 403, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Forbidden' );
        $arrayStatus[] = array( 'code' => 404, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Not Found' );
        $arrayStatus[] = array( 'code' => 429, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Rate Limit' );
        $arrayStatus[] = array( 'code' => 500, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Server Error' );
        $arrayStatus[] = array( 'code' => 502, 'icon' => 'inactive', 'caption' => 'Instanz ist fehlerhaft: Bad Gateway' );

        $arraySort = array();
        #$arraySort = array( 'column' => 'DeviceName', 'direction' => 'ascending' );

        $arrayElements = array();
        $arrayElements[] = array( 'type' => 'Label','bold' => true, 'label' => $this->Translate('UniFi Device Gateway'));
        $arrayElements[] = array( 'type' => 'Label', 'label' => 'Bitte API Key unter "UniFi Network > Settings > Control Plane > Integrations" erzeugen');
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'ServerAddress', 'caption' => 'Unifi Device IP', 'validate' => "^(([a-zA-Z0-9\\.\\-\\_]+(\\.[a-zA-Z]{2,3})+)|(\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b))$" );
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'APIKey', 'caption' => 'APIKey' );
        $arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'applicationVersion', 'caption' => $this->Translate('Show Application Version') );
        $arrayElements[] = array( 'type' => 'Select', 'name' => 'Site', 'caption' => 'Site', 'options' => $arrayOptions );

        if ( !empty( $APIKey && $this->GetStatus() === 102)) {
            if ($this->ReadPropertyBoolean("applicationVersion")) {
                 $this->SetValue('applicationVersion', $this->getNetworkVersion());
            }
            $arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Network Application Version: ').$this->getNetworkVersion() );           
        } else {
            $arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Network Application Version: not found') );
        }
        $arrayActions = array();

        return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );

    }

    public function getApiData( string $endpoint = '' ):array {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, 'https://'.$ServerAddress.'/proxy/network/integrations/v1/sites'.$endpoint );
        curl_setopt( $ch, CURLOPT_HTTPGET, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey ) );
        curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
        $RawData = curl_exec( $ch );
        curl_close( $ch );
        if ($RawData === false) {
            // Handle error
            $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
            $this->SetStatus( 201 ); // Set status to error
            return [];
        }
        $JSONData = json_decode( $RawData, true );
        if ( isset( $JSONData[ 'statusCode' ] ) ) {
            if ($JSONData[ 'statusCode' ]<> 200 && $JSONData[ 'statusCode' ]<> 404 && $JSONData[ 'statusCode' ]<> 500) {
                // instance inactive
			    $this->SetStatus( $JSONData[ 'statusCode' ] );
            }        
        }
        return $JSONData;
    }
    public function getApiDataPost( string $endpoint = '', string $PostData = '' ):array {
			$ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
            $APIKey = $this->ReadPropertyString( 'APIKey' );
			if ($APIKey == '') {
				$this->SendDebug("UnifiSiteApi", "API Key is empty", 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}

			$ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, 'https://'.$ServerAddress.'/proxy/network/integrations/v1/sites'.$endpoint );
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $PostData );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey, 'Content-Type: application/json' ));
			curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
			$RawData = curl_exec( $ch );
			curl_close( $ch );
			if ($RawData === false) {
				// Handle error
				$this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			$JSONData = json_decode( $RawData, true );
			if ( isset( $JSONData[ 'statusCode' ] ) ) {
				if ($JSONData[ 'statusCode' ]<> 200) {
					// instance inactive
                    $this->SendDebug("UnifiGW", "Curl error: " . json_encode($JSONData), 0);
					return [];
				}        
			}
			$this->SendDebug("UnifiGW", "GetApiDataPost: " . $RawData, 0);
			return [];
		}

    public function getApiDataPut( string $endpoint = '', string $PutData = '' ):array {
            $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
            $APIKey = $this->ReadPropertyString( 'APIKey' );
            if ($APIKey == '') {
                $this->SendDebug("UnifiSiteApi", "API Key is empty", 0);
                $this->SetStatus( 201 ); // Set status to error
                return [];
            }

            //https://192.168.178.1/proxy/network/integration/v1/sites/{siteId}/wifi/broadcasts/{wifiBroadcastId}
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, 'https://'.$ServerAddress.'/proxy/network/integrations/v1/sites'.$endpoint );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $PutData );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey, 'Content-Type: application/json' ));
            curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
            $RawData = curl_exec( $ch );
            curl_close( $ch );
            if ($RawData === false) {
                // Handle error
                $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
                $this->SetStatus( 201 ); // Set status to error
                return [];
            }
            $JSONData = json_decode( $RawData, true );
            if ( isset( $JSONData[ 'statusCode' ] ) ) {
                if ($JSONData[ 'statusCode' ]<> 200) {
                    // instance inactive
                    $this->SendDebug("UnifiGW", "Curl error: " . json_encode($JSONData), 0);
                    return [];
                }
            }
            $this->SendDebug("UnifiGW", "GetApiDataPut: " . $RawData, 0);
            return [];
        }

    public function getSiteWans(string $siteName): array {
        if ($siteName === '') {
            return [];
        }
        $siteID=$this->getSiteID($siteName);
        return $this->getApiData('/'.$siteID.'/wans');
    }

    public function getWifis(): array {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData=$this->getApiData('/'.$siteID.'/wifi/broadcasts');
        $this->SendDebug("UnifiGW", "getSiteWifis: " .json_encode($JSONData) , 0);
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['error'] ) ) {
                if ($JSONData['error']['code']<> 200) {
                    // instance inactive
                    $this->SetStatus( $JSONData['error']['code'] );
                    $value[] = [
                        'caption'=>'errror',
                        'value'=> 'default'
                    ];
                    return $value;
                }        
            }
            if ( isset( $JSONData['data'] ) ) {
                $sites = $JSONData[ 'data' ];
            foreach ( $sites as $site ) {
                $value[] = [
                    'caption'=>$site[ 'name' ],
                    'value'=> $site[ 'id' ]
                ];
            }
            } else {
                $value[] = [
                    'caption'=>'default',
                    'value'=> 'default'
                ];
            }            
            return $value;
        }
        return [];
    }

    public function getNetworks(): array {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData=$this->getApiData('/'.$siteID.'/networks');
        $this->SendDebug("UnifiGW", "getNetworks: " .json_encode($JSONData) , 0);
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['error'] ) ) {
                if ($JSONData['error']['code']<> 200) {
                    // instance inactive
                    $this->SetStatus( $JSONData['error']['code'] );
                    $value[] = [
                        'caption'=>'errror',
                        'value'=> 'default'
                    ];
                    return $value;
                }        
            }
            if ( isset( $JSONData['data'] ) ) {
                $sites = $JSONData[ 'data' ];
            foreach ( $sites as $site ) {
                $value[] = [
                    'caption'=>$site[ 'name' ],
                    'value'=> $site[ 'id' ]
                ];
            }
            } else {
                $value[] = [
                    'caption'=>'default',
                    'value'=> 'default'
                ];
            }            
            return $value;
        }
        return [];
    }

    public function getNetworkDetails(string $networkID): array {
        if ($networkID === '') {
            return [];
        }
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        return $this->getApiData('/'.$siteID.'/networks/'.$networkID);
    }
    public function getWifiDetails(string $wifiID): array {
        if ($wifiID === '') {
            return [];
        }
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        return $this->getApiData('/'.$siteID.'/wifi/broadcasts/'.$wifiID);
    }

    public function getSites():array {
        $JSONData = $this->getApiData();       
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['error'] ) ) {
                if ($JSONData['error']['code']<> 200) {
                    // instance inactive
                    $this->SetStatus( $JSONData['error']['code'] );
                    $value[] = [
                        'caption'=>'default',
                        'value'=> 'default'
                    ];
                    return $value;
                }        
            }
            if ( isset( $JSONData['data'] ) ) {
                $sites = $JSONData[ 'data' ];
            foreach ( $sites as $site ) {
                $value[] = [
                    'caption'=>$site[ 'internalReference' ],
                    'value'=> $site[ 'internalReference' ]
                ];
            }
            } else {
                $value[] = [
                    'caption'=>'default',
                    'value'=> 'default'
                ];
            }            
            return $value;
        }
        return [];
    }

    public function getNetworkVersion():string {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        if (empty($ServerAddress) || empty($APIKey)) {
            $this->SetStatus( 104 ); // Set status to error
            return '';
        }

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, 'https://'.$ServerAddress.'/proxy/network/integrations/v1/info');
        curl_setopt( $ch, CURLOPT_HTTPGET, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-API-KEY:'.$APIKey ) );
        curl_setopt( $ch, CURLOPT_SSLVERSION, 'CURL_SSLVERSION_TLSv1' );
        $RawData = curl_exec( $ch );
        curl_close( $ch );
        if ($RawData === false) {
            // Handle error
            $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
            $this->SetStatus( 201 ); // Set status to error
            return '';
        }
        $JSONData = json_decode( $RawData, true );
        $this->SendDebug("UnifiGW", json_encode($JSONData), 0);
        if ( isset( $array[ 'statusCode' ] ) ) {
            if ($array[ 'statusCode' ]<> 200) {
                // instance inactive
			    $this->SetStatus( $array[ 'statusCode' ] );
                return '';               
            }       
        }
        if (isset($JSONData['applicationVersion']) && !empty($JSONData['applicationVersion'])) {
            // instance active
            $this->SetStatus( 102 );
            return $JSONData['applicationVersion'];
        }
        return 'notfound';        
    }

    private function networkVersionCheck(): bool
    {
        $version = $this->getNetworkVersion();
        if ($version === '' || $version === 'notfound') {
            $this->SendDebug('UnifiGW', 'Unable to determine network version, skipping wifi details request.', 0);
            return false;
        }
        $numericVersion = $this->parseNumericVersion($version);
        if ($numericVersion < 10.0) {
            $this->SendDebug('UnifiGW', 'Network version ' . $version . ' is below 10.0. getWifiDetails requires >= 10.0.', 0);
            return false;
        }
        return true;
    }

    private function parseNumericVersion(string $version): float
    {
        if (preg_match('/\d+(?:\.\d+)?/', $version, $matches) === 1) {
            return (float) $matches[0];
        }
        return 0.0;
    }

    public function getSiteID( string $site = 'default' ):string {
        $JSONData = $this->getApiData();
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['data'] ) ) {
                foreach ( $JSONData[ 'data' ] as $item ) {
                if ( $item[ 'internalReference' ] == $site ) {
                    return $item[ 'id' ];
                }
            }
            } else {
                // instance inactive
                $this->SetStatus( 201 );
            }            
        }
        return '';
    }


    public function getWifiID( string $siteID, string $wifi  ):string {
        $JSONData = $this->getApiData('/'.$siteID.'/wifi/broadcasts');
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['data'] ) ) {
                foreach ( $JSONData[ 'data' ] as $item ) {
                if ( $item[ 'name' ] == $wifi ) {
                    return $item[ 'id' ];
                }
            }
            } else {
                // instance inactive
                $this->SetStatus( 201 );
            }            
        }
        return '';
    }

    public function getClients():array {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients?limit=200' );
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if (isset($JSONData[ 'data' ])) {
                $clients = $JSONData[ 'data' ];
                usort( $clients, function ( $a, $b ) {
                    return strcmp($a['name'], $b['name']);
                });

                foreach ( $clients as $client ) {
                    $value[] = [
                        'caption'=>$client[ 'name' ],
                        'value'=> isset( $client[ 'id' ] ) ? $client[ 'id' ] : 'missing'
                    ];
                }
            } else {
                $value[] = [
                    'caption'=>'default',
                    'value'=> 'default'
                ];
            }            
            return $value;
        }
    }

    public function updateNetworkSettings(string $networkID, string $jsonPayload):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        //https://192.168.178.1/proxy/network/integration/v1/sites/{siteId}/wifi/broadcasts/{wifiBroadcastId}
        $this->SendDebug("UnifiGW", "updateNetworkSettings: " . $jsonPayload, 0);
        $this->SendDebug("UnifiGW", "updateNetworkSettings: " . '/'.$siteID.'/networks/'.$networkID.'/', 0);
        $JSONData=$this->getApiDataPut( '/'.$siteID.'/networks/'.$networkID, $jsonPayload );
        return json_encode($JSONData);
    }

    public function updateWifiSettings(string $wifiID, string $jsonPayload):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        //https://192.168.178.1/proxy/network/integration/v1/sites/{siteId}/wifi/broadcasts/{wifiBroadcastId}
        $this->SendDebug("UnifiGW", "updateWifiSettings: " . $jsonPayload, 0);
        $this->SendDebug("UnifiGW", "updateWifiSettings: " . '/'.$siteID.'/wifi/broadcasts/'.$wifiID.'/', 0);
        $JSONData=$this->getApiDataPut( '/'.$siteID.'/wifi/broadcasts/'.$wifiID, $jsonPayload );
        return json_encode($JSONData);
    }

    public function setPortCycle(string $deviceID, int $port):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $PostData = json_encode([
            'action' => 'POWER_CYCLE'
               ]);
        $this->SendDebug("UnifiGW", "setPortCycle: " . $PostData, 0);
        $this->SendDebug("UnifiGW", "setPortCycle: " . '/'.$siteID.'/devices/'.$deviceID.'/interfaces/ports/'.$port.'/actions', 0);
        $JSONData=$this->getApiDataPost( '/'.$siteID.'/devices/'.$deviceID.'/interfaces/ports/'.$port.'/actions', $PostData );
        return json_encode($JSONData);
    }

    public function setRestartDevice(string $deviceID):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $PostData = json_encode([
            'action' => 'RESTART'
               ]);
        $this->SendDebug("UnifiGW", "setRestartDevice: " . $PostData, 0);
        $this->SendDebug("UnifiGW", "setRestartDevice: " . '/'.$siteID.'/devices/'.$deviceID.'/actions', 0);
        $JSONData=$this->getApiDataPost( '/'.$siteID.'/devices/'.$deviceID.'/actions', $PostData );
        return json_encode($JSONData);
    }

     public function getClientData(string $clientID) {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients/'.$clientID );
        return json_encode($JSONData);
    }

	public function getDevices() {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );

        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if (isset($JSONData[ 'data' ])) {
                $devices = $JSONData[ 'data' ];
                usort( $devices, function ( $a, $b ) {
                    return strcmp($a['name'], $b['name']);
                });

                foreach ( $devices as $device ) {
                    $value[] = [
                        'caption'=>$device[ 'name' ],
                        'value'=> isset( $device[ 'id' ] ) ? $device[ 'id' ] : 'missing'
                    ];
                }
            } else {
                $value[] = [
                    'caption'=>'default',
                    'value'=> 'default'
                ];
            }
            return $value;
        }
    }

    public function getDeviceStats(string $deviceID) {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID.'/statistics/latest' );
        return json_encode($JSONData);        
    }

    public function getDeviceData(string $deviceID):string {
            $site = $this->ReadPropertyString( 'Site' );
            $siteID = $this->getSiteID( $site );        
            $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID );
            return json_encode($JSONData);
        }

     public function getDeviceName(string $deviceID):string {
            $this->SendDebug("UnifiGW", "getDeviceName: " . $deviceID, 0);
            $site = $this->ReadPropertyString( 'Site' );
            $siteID = $this->getSiteID( $site );        
            $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID );
            if (isset($JSONData['name'])){
                return $JSONData['name'];
            }
            return '';
        }
    private function getInstanceIDForGuid( $id, $guid )
    {
        $instanceIDs = IPS_GetInstanceListByModuleID( $guid );
        foreach ( $instanceIDs as $instanceID ) {
            if ( IPS_GetProperty( $instanceID, 'ID' ) == $id ) {
                return $instanceID;
            }
        }
        return 0;
    }

    public function getDevicesConfig():string {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );

        if ( is_array( $JSONData ) && isset( $JSONData ) )
        {
            $devices = $JSONData[ 'data' ];
            $this->SendDebug("UnifiGW", json_encode($devices), 0);
            usort( $devices, function ( $a, $b ) {
                return $a[ 'name' ]>$b[ 'name' ];
                });
            foreach ( $devices as $device ) {                         
                $addValue = array(
                    'Name'	=>$device[ 'name' ],
                    'Type'	=>$device[ 'model' ],
                    'ID'		=>isset( $device[ 'id' ] ) ? $device[ 'id' ] : 'missing' ,
                    'IP'		=>$device[ 'ipAddress' ],
                    'instanceID'	=>$this->getInstanceIDForGuid( isset( $device[ 'id' ] ) ? $device[ 'id' ] : '', '{19A9D2AF-BD00-461A-58E1-7BF7A0CA19A6}' )
                );
                if (isset($device['id']) and !empty($device['id'])) {
                    $addValue['create'] = array(
                        'moduleID'      => '{19A9D2AF-BD00-461A-58E1-7BF7A0CA19A6}',
                        'configuration' => [
                            'ID'	=> isset( $device[ 'id' ] ) ? $device[ 'id' ] : ''
                        ],
                        'name' => $device[ 'name' ]
                    );
                }
                $value[] = $addValue;
            }
        }
        $JSONData = $this->getApiData( '/'.$siteID.'/clients?limit=200' );
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            $clients = $JSONData[ 'data' ];
            usort( $clients, function ( $a, $b ) {
                return $a[ 'name' ]>$b[ 'name' ];
            });
            foreach ( $clients as $client )
                {
                   $addValue = array(
                        'Name'	=>$client[ 'name' ],
                        'Type'	=>'Client-'.  (isset( $client[ 'type' ] ) ? $client[ 'type' ] : 'missing'),
                        'ID'		=>isset( $client[ 'id' ] ) ? $client[ 'id' ] : 'missing' ,
                        'IP'		=>isset( $client[ 'ipAddress' ] ) ? $client[ 'ipAddress' ] : '',
                        'instanceID'	=>$this->getInstanceIDForGuid( isset( $client[ 'id' ] ) ? $client[ 'id' ] : '', '{75E5E0AD-02F4-61E0-E1AF-57F66DAF7381}' )
                        );
                        if (isset($client['id']) and !empty($client['id'])) {
                            $addValue['create'] = array(
                            'moduleID'      => '{75E5E0AD-02F4-61E0-E1AF-57F66DAF7381}',
                            'configuration' => [
                                'ID'	=> isset( $client[ 'id' ] ) ? $client[ 'id' ] : ''
                            ],
                            'name' => $client[ 'name' ]
                            );
                        }
                    $value[] = $addValue;
                }
            }
        if ($this->networkVersionCheck()) {  
            $JSONData = $this->getApiData( '/'.$siteID.'/wifi/broadcasts?limit=200' );
            if ( is_array( $JSONData ) && isset( $JSONData ) ) {
                $wifis = $JSONData[ 'data' ];
                usort( $wifis, function ( $a, $b ) {
                    return $a[ 'name' ]>$b[ 'name' ];
                });
                foreach ( $wifis as $wifi )
                    {
                    $addValue = array(
                            'Name'	=>$wifi[ 'name' ],
                            'Type'	=>'Wifi-'.  (isset( $wifi[ 'type' ] ) ? $wifi[ 'type' ] : 'missing'),
                            'ID'		=>isset( $wifi[ 'id' ] ) ? $wifi[ 'id' ] : 'missing' ,
                            'IP'		=>'Wifi-Network',
                            'instanceID'	=>$this->getInstanceIDForGuid( isset( $wifi[ 'id' ] ) ? $wifi[ 'id' ] : '', '{CA0DC52D-37B8-32A1-C017-8BCB7EF2FAFB}' )
                            );
                            if (isset($wifi['id']) and !empty($wifi['id'])) {
                                $addValue['create'] = array(
                                'moduleID'      => '{CA0DC52D-37B8-32A1-C017-8BCB7EF2FAFB}',
                                'configuration' => [
                                    'ID'	=> isset( $wifi[ 'id' ] ) ? $wifi[ 'id' ] : ''
                                ],
                                'name' => $wifi[ 'name' ]
                                );
                            }
                        $value[] = $addValue;
                    }
                }
            $JSONData = $this->getApiData( '/'.$siteID.'/networks?limit=200' );
            if ( is_array( $JSONData ) && isset( $JSONData ) ) {
                $networks = $JSONData[ 'data' ];
                usort( $networks, function ( $a, $b ) {
                    return $a[ 'name' ]>$b[ 'name' ];
                });
                foreach ( $networks as $network )
                    {
                    $addValue = array(
                            'Name'	=>$network[ 'name' ],
                            'Type'	=>(isset( $network[ 'management' ] ) ? $network[ 'management' ] : 'missing'),
                            'ID'		=>isset( $network[ 'id' ] ) ? $network[ 'id' ] : 'missing' ,
                            'IP'		=>'VlanID-'.(isset( $network[ 'vlanId' ] ) ? $network[ 'vlanId' ] : ''),
                            'instanceID'	=>$this->getInstanceIDForGuid( isset( $network[ 'id' ] ) ? $network[ 'id' ] : '', '{CCD58E88-A596-0F92-38C8-72BCE28BCDED}' )
                            );
                            if (isset($network['id']) and !empty($network['id'])) {
                                $addValue['create'] = array(
                                'moduleID'      => '{CCD58E88-A596-0F92-38C8-72BCE28BCDED}',
                                'configuration' => [
                                    'ID'	=> isset( $network[ 'id' ] ) ? $network[ 'id' ] : ''
                                ],
                                'name' => $network[ 'name' ]
                                );
                            }
                        $value[] = $addValue;
                    }
                }
            }
        return json_encode($value);
    }
}