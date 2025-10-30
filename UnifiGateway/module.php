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
                        'Type'	=>'Client',
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
                return json_encode($value);
    }
}