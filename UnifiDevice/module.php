<?php

declare(strict_types=1);
	class UnifiDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->ConnectParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
			$this->RegisterPropertyString( 'ID', '' );
        	$this->RegisterPropertyInteger( 'Timer', '0' );
        	$this->RegisterTimer( 'Collect Data', 0, "UNIFIDV_Send(\$_IPS['TARGET'],'getDeviceData');" );
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
			$this->MaintainVariable( 'DeviceName', $this->Translate( 'Device Name' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'ID', $this->Translate( 'Device ID' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'DeviceModel', $this->Translate( 'Device Model' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'DeviceIP', $this->Translate( 'Device IP' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'Firmware', $this->Translate( 'Firmware' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'FirmwareUpdate', $this->Translate( 'FirmwareUpdate' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'circle-info', 'OPTIONS'=>'[{"ColorDisplay":1692672,"Value":false,"Caption":"Aktuell","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1},{"ColorDisplay":16711680,"Value":true,"Caption":"Update Verfügbar","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":16711680,"Color":-1}]' ], $vpos++, 1 );
			$this->MaintainVariable( 'UptimeSec', $this->Translate( 'UptimeSec' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'FORMAT'=> 2 ], $vpos++, 1 );
			$this->MaintainVariable( 'UplinkTX', $this->Translate( 'UplinkTX' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 3 , 'SUFFIX'=> 'Mbit/s' , 'ICON'=> 'network-wired'] , $vpos++, 1 );
			$this->MaintainVariable( 'UplinkRX', $this->Translate( 'UplinkRX' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 3 , 'SUFFIX'=> 'Mbit/s' , 'ICON'=> 'network-wired'], $vpos++, 1 );
			$this->MaintainVariable( 'Online', $this->Translate( 'Online' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'network-wired','OPTIONS'=>'[{"ColorDisplay":16077123,"Value":false,"Caption":"Offline","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":16077123,"Color":-1},{"ColorDisplay":1692672,"Value":true,"Caption":"Online","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1}]'], $vpos++, 1 );

			$TimerMS = $this->ReadPropertyInteger( 'Timer' ) * 1000;
			$this->SetTimerInterval( 'Collect Data', $TimerMS );
			if ( 0 == $TimerMS )
			{
				// instance inactive
				$this->SetStatus( 104 );
			} else {
				// instance active
				$this->SetStatus( 102 );
			}
		}

		public function Send(string $api)
		{
			if ($this->HasActiveParent()) {
				$this->SendDataToParent(json_encode(['DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
					'Api' => $api,
					'InstanceID' => $this->InstanceID
					]));
			}			
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString,true);
			If ($data['id']== $this->InstanceID) {
				//IPS_LogMessage('UNIFIDV-'.$this->InstanceID,utf8_decode($data['data']));
				switch($data['Api']) {
					case "getDevices":
						$this->UpdateFormField("ID", "options", $data['data']);
						$this->SetBuffer("devices", $data['data']);
						break;
					case "getDeviceStats":
						$JSONData = json_decode($data['data'],true);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							#var_dump( $JSONData );
							$this->SetValue( 'UptimeSec', (( isset($JSONData[ 'uptimeSec' ]) ) ? $JSONData[ 'uptimeSec' ] : 0) );
							$this->SetValue( 'UplinkTX', round( (( isset($JSONData[ 'uplink' ][ 'txRateBps' ]) ) ? $JSONData[ 'uplink' ][ 'txRateBps' ]/1000/1000 : 0),3 ) );
							$this->SetValue( 'UplinkRX', round( (( isset($JSONData[ 'uplink' ][ 'rxRateBps' ]) ) ? $JSONData[ 'uplink' ][ 'rxRateBps' ]/1000/1000 : 0),3 ) );
						}


					break;
					case "getDeviceData":
						$JSONData = json_decode($data['data'],true);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							if ( isset( $JSONData[ 'statusCode' ] ) ) {
								$this->SetValue( 'Online', false );
							} else {
								$this->SetValue( 'DeviceName', $JSONData[ 'name' ] );
								$this->SetValue( 'ID', $JSONData[ 'id' ] );
								$this->SetValue( 'DeviceModel', $JSONData[ 'model' ] );
								$this->SetValue( 'DeviceIP', $JSONData[ 'ipAddress' ] );
								$this->SetValue( 'Firmware', $JSONData[ 'firmwareVersion' ] );
								$this->SetValue( 'FirmwareUpdate', $JSONData[ 'firmwareUpdatable' ] );
								$this->SetValue( 'Online', ( $JSONData[ 'state' ] == 'ONLINE' ) ? true : false );
								if ( isset( $JSONData[ 'interfaces' ] ) ) {
									if ( isset( $JSONData[ 'interfaces' ][ 'ports' ] ) ) {
										$ports = $JSONData[ 'interfaces' ][ 'ports' ];
										usort( $ports, function ( $a, $b ) {
														return intval($a[ 'idx' ])>intval($b[ 'idx' ]);
														});
										$vpos = 1000;
										foreach ( $ports as $port ) {
											if ($port[ 'state' ]== 'UP') {                                
												if ($port[ 'speedMbps' ]<=100) {                                   
												$colSymbol=16776960; //gelb
												} else {
													$colSymbol=1692672; //gruen
												}                                
											} else {
												$colSymbol=16077123; //rot
											}

											$this->MaintainVariable( 'Port_'.$port[ 'idx' ], $this->Translate( 'Port '.$port[ 'idx' ] ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'ethernet'], $vpos++, 1 );
											$PortText='';
											if (isset($port[ 'speedMbps' ])) {
												if ($port[ 'speedMbps' ]>=1000) {
													$speed=($port[ 'speedMbps' ]/1000).'Gbit/s';                                
												} else {
													$speed=$port[ 'speedMbps' ].'Mbit/s';
												}
											} else {
												$speed=$port[ 'state' ];
											}

											if ( isset( $port[ 'poe' ] ) ) {
												if ($port[ 'poe' ]['state']=='UP') {
													$colSymbol=1692672;
													$poe=$port[ 'poe' ]['standard'];
												}else {
													$colSymbol=16077123;
													$poe=$port[ 'poe' ]['state'];
												}
												$this->MaintainVariable( 'Port_'.$port[ 'idx' ].'POE', $this->Translate( 'Port '.$port[ 'idx' ].'-POE' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'ethernet'], $vpos++, 1 );
												$this->SetValue( 'Port_'.$port[ 'idx' ].'POE', $poe);
											} else {
												$vpos++;
											}                           
											$this->SetValue( 'Port_'.$port[ 'idx' ], $speed);
										}
									}
									if ( isset( $JSONData[ 'interfaces' ][ 'radios' ] ) ) {
										$radios = $JSONData[ 'interfaces' ][ 'radios' ];
										$vpos = 2000;
										foreach ( $radios as $radio ) {
											//$radio[ 'channel'] prüfen ob vorhanden
											$vpos++;
											if (isset($radio[ 'channel'])) {
												$colSymbol=1692672; //gruen
												$radioTxt=$radio[ 'wlanStandard'].' - '.$radio[ 'channelWidthMHz' ].'MHz';
											} else {
												$colSymbol=16077123; //rot
												$radioTxt=$radio[ 'wlanStandard'];
											}
											$this->MaintainVariable( 'Port_'.$vpos, $this->Translate( 'WLAN '.$radio[ 'frequencyGHz' ].'GHz'), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'wifi'], $vpos, 1 );
											$this->SetValue( 'Port_'.$vpos, $radioTxt );
										}
									}
								}
							}
						}
						$this->Send("getDeviceStats");					
						//IPS_LogMessage('UNIFICL-'.$this->InstanceID,var_dump($array));						
						break;
				}
			}			
		}

		public function GetConfigurationForm(){       
			if ($this->HasActiveParent()) {
				$this->Send("getDevices");
			}	
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );

			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'label' => 'UniFi Device' ); 
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );

			$Bufferdata = $this->GetBuffer("devices");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}		
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Device ID', 'options' => $arrayOptions );

			$arrayActions = array();

			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Devices Holen', 'onClick' => 'UNIFIDV_Send($id,"getDevices");' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Daten Holen', 'onClick' => 'UNIFIDV_Send($id,"getDeviceData");' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Stats Holen', 'onClick' => 'UNIFIDV_Send($id,"getDeviceStats");' );

			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	    }
	}