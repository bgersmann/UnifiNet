<?php

declare(strict_types=1);

// CLASS UnifiWifi
class UnifiWifi extends IPSModule
{   
    public function Create()
    {
        //Never delete this line!
        parent::Create();
	
        $this->ConnectParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
		$this->RegisterPropertyString( 'ID', '' );
        $this->RegisterPropertyInteger( 'Timer', 0 );
		$this->RegisterTimer( 'Collect Data', 0, "UNIFIWF_Send(\$_IPS['TARGET'],'getWifiDetails','');" );
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function Send(string $api, string $param1)
		{
			if ($this->HasActiveParent()) {
				$data=$this->SendDataToParent(json_encode(['DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
					'Api' => $api,
					'InstanceID' => $this->InstanceID,
					'Param1' => $param1
					]));
				if (!$data) {
					$this->SendDebug("UnifiWF", "Send Data Error: " . $api, 0);
					return;
				};
				switch($api) {
                    case "getWifis":
						$siteData=json_encode(unserialize($data));
						$this->UpdateFormField("ID", "options", $siteData);
						$this->SetBuffer("Wifis", $siteData);
                        $this->SendDebug("UnifiWF", "Get Wifi Data: " . $siteData, 0);
						break;
                    case "getWifiDetails":
                       $JSONData=unserialize($data);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							if (isset($JSONData['code']) && $JSONData['code']==600) {
								// UniFi Network Version zu alt. Erst ab 10+ verfügbar
								$this->SetStatus( 600 );
								return;
							} 							
							$this->SendDebug("UnifiWF", json_encode($JSONData), 0);
                            $type= (( isset($JSONData[ 'type' ]) ) ? $JSONData[ 'type' ] : 'STANDARD');
							$this->SetValue( 'WifiName', (( isset($JSONData[ 'name' ]) ) ? $JSONData[ 'name' ] : 'Default') );
							$this->SetValue( 'WifiEnabled',  (( isset($JSONData[ 'enabled' ]) ) ? $JSONData[ 'enabled' ] : false)  );
							$this->SetValue( 'WifiType', $type);
							$this->SetValue( 'WifiSecurity', (( isset($JSONData[ 'securityConfiguration' ]['type']) ) ? $JSONData[ 'securityConfiguration' ]['type'] : 'STANDARD') );
							                
                            $frequenciesValue = '';
                            if ($type=="IOT_OPTIMIZED") {
                                    // IoT-Optimized uses only 2.4 GHz
                                    $frequenciesValue = '2.4';
                                    $this->SetValue('WifiFrequencies', $frequenciesValue);                                    
                            }
							if (isset($JSONData['broadcastingFrequenciesGHz']) && is_array($JSONData['broadcastingFrequenciesGHz']) && $type!="IOT_OPTIMIZED") {   
								$bands = $JSONData['broadcastingFrequenciesGHz'];
								sort($bands, SORT_NUMERIC);
								$normalized = array_map(function ($value) {
									if (is_numeric($value)) {
										return rtrim(rtrim(number_format((float)$value, 1, '.', ''), '0'), '.');
									}
									return (string)$value;
								}, $bands);
								$frequenciesValue = implode('|', $normalized);
							}
							$this->SetValue('WifiFrequencies', $frequenciesValue);
							
						}


                        break;
                    }
			}
		}



    public function GetConfigurationForm()
    {
       		if ($this->HasActiveParent()) {
				$this->Send('getWifis','');
			}	
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );
			$arrayStatus[] = array( 'code' => 600, 'icon' => 'inactive', 'caption' => 'UniFi Network Version zu alt. Erst ab 10+ verfügbar' );
			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'bold' => true, 'label' => $this->Translate('UniFi Wifi Controller'));
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Set Timer to activate instance. The instance will then automatically collect data from the device at the specified interval.')); 
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );

			$Bufferdata = $this->GetBuffer("Wifis");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Wifi ID', 'options' => $arrayOptions );
			
			$arrayActions = array();
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Wifis'),'width' => '220px', 'onClick' => 'UNIFIWF_Send($id,"getWifis","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Wifi Details'),'width' => '220px', 'onClick' => 'UNIFIWF_Send($id,"getWifiDetails","");' );
			$arrayActions[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	}

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $vpos = 100;
        $this->MaintainVariable( 'WifiName', $this->Translate( 'Name' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
		$this->MaintainVariable(
			'WifiType',
			$this->Translate('Type'),
			3,
			[
				'ICON' => 'circle-info',
				'DECIMAL_SEPARATOR' => '',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 1,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'INTERVALS' => '[{"IntervalMinValue":0,"IntervalMaxValue":0,"ConstantActive":true,"ConstantValue":"Standard","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"ColorDisplay":-1},{"IntervalMinValue":1,"IntervalMaxValue":1,"ConstantActive":true,"ConstantValue":"IOT-Optimized","ConversionFactor":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":false,"DigitsValue":0,"IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"ColorDisplay":-1}]',
				'INTERVALS_ACTIVE' => true,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
		$this->MaintainVariable(
			'WifiEnabled',
			$this->Translate('Aktiv'),
			0,
			[
				'ICON' => 'shield-halved',
				'DECIMAL_SEPARATOR' => 'Client',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 100,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'OPTIONS' => '[{"ColorDisplay":16077123,"Value":false,"Caption":"Inaktiv","IconValue":"wifi-slash","IconActive":true,"ColorActive":true,"ColorValue":16077123,"Color":-1},{"ColorDisplay":1692672,"Value":true,"Caption":"Aktiv","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1}]',
				'INTERVALS_ACTIVE' => false,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
		$this->EnableAction('WifiEnabled');
		$this->MaintainVariable(
			'WifiSecurity',
			$this->Translate('Security'),
			3,
			[
				'ICON' => 'shield-halved',
				'DECIMAL_SEPARATOR' => '',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 6,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'OPTIONS' => '[{"Value":"OPEN","Caption":"Offen","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA2_PERSONAL","Caption":"WPA2 Personal","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA3_PERSONAL","Caption":"WPA3 Personal","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA2_WPA3_PERSONAL","Caption":"WPA2+WPA3 Personal","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA2_ENTERPRISE","Caption":"WPA2 Enterprise","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA3_ENTERPRISE","Caption":"WPA3 Enterprise","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"WPA2_WPA3_ENTERPRISE","Caption":"WPA2 + WPA3 Enterprise","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1}]',
				'INTERVALS_ACTIVE' => true,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => '',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
        $this->MaintainVariable(
			'WifiFrequencies',
			$this->Translate('Frequencies'),
			3,
            [
				'ICON' => 'wifi',
				'DECIMAL_SEPARATOR' => 'Client',
				'COLOR' => -1,
				'MIN' => 0,
				'DIGITS' => 0,
				'MAX' => 100,
				'PRESENTATION' => '{3319437D-7CDE-699D-750A-3C6A3841FA75}',
				'OPTIONS' => '[{"Value":"2.4","Caption":"2,4 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"5","Caption":"5 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"6","Caption":"6 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"2.4|5","Caption":"2,4 + 5 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"2.4|6","Caption":"2,4 + 6 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"5|6","Caption":"5 + 6 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1},{"Value":"2.4|5|6","Caption":"2,4 + 5 + 6 GHz","IconActive":false,"IconValue":"","ColorActive":false,"ColorValue":-1,"Color":-1,"ColorDisplay":-1}]',
			    'INTERVALS_ACTIVE' => false,
				'MULTILINE' => false,
				'PERCENTAGE' => false,
				'PREFIX' => '',
				'SUFFIX' => ' GHz',
				'THOUSANDS_SEPARATOR' => '',
				'USAGE_TYPE' => 0
			],
			$vpos++,
			1
		);
        // Set status
		$TimerMS = $this->ReadPropertyInteger( 'Timer' ) * 1000;
		$this->SetTimerInterval( 'Collect Data', $TimerMS );
		if ( 0 == $TimerMS )
		{
			// instance inactive
			$this->SetStatus( 104 );
		} else {
			// instance active
			$this->SetStatus( 102 );
			$this->Send('getWifiDetails','');
		}
    }    
    
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value, 0);
        // TODO: Replace identifier
        switch ($ident) {
            case 'WifiEnabled':
				$jsonPayload = $this->buildWifiUpdateJson($value);
				if ($jsonPayload !== '') {
					$this->Send('updateWifiSettings', $jsonPayload);
				}
				usleep(100000);
				$this->Send('getWifiDetails','');
				//$this->SetValue($ident, $value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'There was no reaction to the action.', 0);
        }
        return;
    }

	private function buildWifiUpdateJson(bool $enabled): string
	{
		if (!$this->HasActiveParent()) {
			$this->SendDebug(__FUNCTION__, 'No parent instance connected', 0);
			return '';
		}
		$request = json_encode([
			'DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
			'Api' => 'getWifiDetails',
			'InstanceID' => $this->InstanceID,
			'Param1' => ''
		]);
		if ($request === false) {
			$this->SendDebug(__FUNCTION__, 'Unable to encode request payload', 0);
			return '';
		}
		$data = $this->SendDataToParent($request);
		if ($data === false || $data === null) {
			$this->SendDebug(__FUNCTION__, 'Parent returned no data for getWifiDetails', 0);
			return '';
		}
		$wifiDetails = @unserialize($data);
		if (!is_array($wifiDetails)) {
			$this->SendDebug(__FUNCTION__, 'Unable to unserialize wifi details', 0);
			return '';
		}
		$network = $wifiDetails['network'] ?? null;
		$security = $wifiDetails['securityConfiguration'] ?? null;
		if (!is_array($network) || !is_array($security)) {
			$this->SendDebug(__FUNCTION__, 'Missing required network or security details', 0);
			return '';
		}
		$type = (string) ($wifiDetails['type'] ?? 'STANDARD');
		$payload = [
			'type' => $type,
			'name' => (string) ($wifiDetails['name'] ?? ''),
			'network' => $network,
			'enabled' => $enabled,
			'securityConfiguration' => $security,
			'multicastToUnicastConversionEnabled' => (bool) ($wifiDetails['multicastToUnicastConversionEnabled'] ?? false),
			'clientIsolationEnabled' => (bool) ($wifiDetails['clientIsolationEnabled'] ?? false),
			'hideName' => (bool) ($wifiDetails['hideName'] ?? false),
			'uapsdEnabled' => (bool) ($wifiDetails['uapsdEnabled'] ?? false),
			'broadcastingFrequenciesGHz' => $this->normalizeBroadcastingFrequencies($wifiDetails['broadcastingFrequenciesGHz'] ?? [], $type),
			'arpProxyEnabled' => (bool) ($wifiDetails['arpProxyEnabled'] ?? false),
			'bssTransitionEnabled' => (bool) ($wifiDetails['bssTransitionEnabled'] ?? false)
		];
		$optionalKeys = [
			'broadcastingDeviceFilter',
			'mdnsProxyConfiguration',
			'multicastFilteringPolicy',
			'basicDataRateKbpsByFrequencyGHz',
			'clientFilteringPolicy',
			'blackoutScheduleConfiguration',
			'hotspotConfiguration',
			'mloEnabled',
			'bandSteeringEnabled',
			'dtimPeriodByFrequencyGHzOverride'
		];
		foreach ($optionalKeys as $key) {
			if (array_key_exists($key, $wifiDetails)) {
				$payload[$key] = $wifiDetails[$key];
			}
		}
		$jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($jsonPayload === false) {
			$this->SendDebug(__FUNCTION__, 'Failed to encode payload JSON', 0);
			return '';
		}
		return $jsonPayload;
	}

	private function normalizeBroadcastingFrequencies($frequencies, string $type): array
	{
		if ($type === 'IOT_OPTIMIZED') {
			return ['2.4'];
		}
		if (!is_array($frequencies)) {
			return [];
		}
		$normalized = array_map(static function ($value) {
			if (is_numeric($value)) {
				return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
			}
			return (string) $value;
		}, $frequencies);
		$unique = array_values(array_unique($normalized));
		sort($unique);
		return $unique;
	}

}