<?php

declare(strict_types=1);
	class UnifiDevice extends IPSModule
	{
        private const COLOR_YELLOW = 16776960;
        private const COLOR_GREEN = 1692672;
        private const COLOR_RED = 16077123;
        private const COLOR_UPDATE = 16711680;
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->ConnectParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
			$this->RegisterPropertyString( 'ID', '' );
        	$this->RegisterPropertyInteger( 'Timer', '0' );
        	$this->RegisterTimer( 'Collect Data', 0, "UNIFIDV_Send(\$_IPS['TARGET'],'getDeviceData','');" );
			$this->RegisterPropertyBoolean("MACAnzeigen", 0);
			$this->RegisterPropertyBoolean("ConnectedClientsAnzeigen", 0);
			$this->RegisterPropertyBoolean("IDAnzeigen", 0);
			$this->RegisterPropertyBoolean("PortsAnzeigen", 0);
			$this->RegisterPropertyBoolean("RadiosAnzeigen", 0);
			$this->RegisterPropertyBoolean("Utilization", 0);
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
			$this->MaintainVariable( 'DeviceName', $this->Translate( 'Device Name' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'ID', $this->Translate( 'Device ID' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("IDAnzeigen") );
			$this->MaintainVariable( 'DeviceModel', $this->Translate( 'Device Model' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'DeviceIP', $this->Translate( 'Device IP' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'Firmware', $this->Translate( 'Firmware' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'FirmwareUpdate', $this->Translate( 'Firmware Update' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'circle-info', 'OPTIONS'=> json_encode( [
				[ 'Caption'=> $this->Translate( 'Up to date' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_GREEN, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> false ],
				[ 'Caption'=> $this->Translate( 'Update available' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_UPDATE, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> true ]
			] ) ], $vpos++, 1 );
						
			$variablenID=@$this->GetIDForIdent("UptimeSec");
			if ($variablenID==false) {
				$variablenID = $this->RegisterVariableInteger('UptimeSec', $this->Translate( 'Uptime' ), [ 'PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'FORMAT'=> 2],$vpos++);
				IPS_SetIcon($variablenID,'circle-info');
			} else {
				$vpos++;
			}
			$this->MaintainVariable( 'UplinkTX', $this->Translate( 'Uplink TX' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 3 , 'SUFFIX'=> 'Mbit/s' , 'ICON'=> 'network-wired'] , $vpos++, 1 );
			$this->MaintainVariable( 'UplinkRX', $this->Translate( 'Uplink RX' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 3 , 'SUFFIX'=> 'Mbit/s' , 'ICON'=> 'network-wired'], $vpos++, 1 );
			$this->MaintainVariable( 'Online', $this->Translate( 'Online' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'network-wired','OPTIONS'=> json_encode( [
				[ 'Caption'=> $this->Translate( 'Offline' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_RED, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> false ],
				[ 'Caption'=> $this->Translate( 'Online' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_GREEN, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> true ]
			] )], $vpos++, 1 );
			$this->MaintainVariable( 'UplinkDevice', $this->Translate( 'Uplink Device' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );

			$this->MaintainVariable( 'CPU', $this->Translate( 'CPU Utilization' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 2 , 'SUFFIX'=> ' %' , 'ICON'=> 'laptop-binary'], $vpos++, $this->ReadPropertyBoolean("Utilization") );
			$this->MaintainVariable( 'Memory', $this->Translate( 'Memory Utilization' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS'=> 2 , 'SUFFIX'=> ' %' , 'ICON'=> 'laptop-binary'], $vpos++, $this->ReadPropertyBoolean("Utilization") );
			$this->MaintainVariable( 'MAC', $this->Translate( 'Device MAC' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("MACAnzeigen") );
			$this->MaintainVariable( 'ConnectedClients', $this->Translate( 'Connected Clients' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("ConnectedClientsAnzeigen") );
		


			$TimerMS = $this->ReadPropertyInteger( 'Timer' ) * 1000;
			$this->SetTimerInterval( 'Collect Data', $TimerMS );
			if ( 0 == $TimerMS )
			{
				// instance inactive
				$this->SetStatus( 104 );
			} else {
				// instance active
				$this->SetStatus( 102 );
				$this->Send('getDeviceData','');
				$this->Send('getDeviceStats','');
			}
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
					$this->SendDebug("UnifiCL", "Send Data Error: " . $api, 0);
					return;
				};
				switch($api) {
					case "getDevices":
						$deviceData=json_encode(unserialize($data));
						$this->UpdateFormField("ID", "options", $deviceData);
						$this->SetBuffer("devices", $deviceData);
						break;

					case "getConnectedClients":
						$JSONData=json_decode(unserialize($data),true);
						if ($this->ReadPropertyBoolean("ConnectedClientsAnzeigen")) {
							if ( is_array( $JSONData ) && isset( $JSONData ) ) {
								$this->SendDebug("UnifiGW", json_encode($JSONData), 0);
								$this->SetValue( 'ConnectedClients', $JSONData['connectedClients']);
							}
						}						
						break;
					case "getDeviceStats":
						$JSONData=json_decode(unserialize($data),true);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							$this->SendDebug("UnifiGW", json_encode($JSONData), 0);
							$this->SetValue( 'UptimeSec', (( isset($JSONData[ 'uptimeSec' ]) ) ? $JSONData[ 'uptimeSec' ] : 0) );
							$this->SetValue( 'UplinkTX', round( (( isset($JSONData[ 'uplink' ][ 'txRateBps' ]) ) ? $JSONData[ 'uplink' ][ 'txRateBps' ]/1000/1000 : 0),3 ) );
							$this->SetValue( 'UplinkRX', round( (( isset($JSONData[ 'uplink' ][ 'rxRateBps' ]) ) ? $JSONData[ 'uplink' ][ 'rxRateBps' ]/1000/1000 : 0),3 ) );
							if ($this->ReadPropertyBoolean("Utilization")) {
								$this->SetValue( 'CPU', (( isset($JSONData[ 'cpuUtilizationPct' ]) ) ? $JSONData[ 'cpuUtilizationPct' ] : 0) );
								$this->SetValue( 'Memory', (( isset($JSONData[ 'memoryUtilizationPct' ]) ) ? $JSONData[ 'memoryUtilizationPct' ] : 0) );
							}
						}
						break;
					case "getDeviceData":
						$JSONData=json_decode(unserialize($data),true);
						if ( is_array( $JSONData ) && isset( $JSONData ) ) {
							if ( isset( $JSONData[ 'statusCode' ] ) ) {
								$this->SetValue( 'Online', false );
							} else {
								$this->SetValue( 'DeviceName', $JSONData[ 'name' ] );
								if ($this->ReadPropertyBoolean("IDAnzeigen")) {
									$this->SetValue( 'ID', $JSONData[ 'id' ] );
								}								
								$this->SetValue( 'DeviceModel', $JSONData[ 'model' ] );
								$this->SetValue( 'DeviceIP', $JSONData[ 'ipAddress' ] );
								$this->SetSummary($JSONData[ 'ipAddress' ]);
								$this->SetValue( 'Firmware', $JSONData[ 'firmwareVersion' ] );
								$this->SetValue( 'FirmwareUpdate', $JSONData[ 'firmwareUpdatable' ] );
								$this->SetValue( 'Online', ( $JSONData[ 'state' ] == 'ONLINE' ) ? true : false );
								if ($this->ReadPropertyBoolean("MACAnzeigen")) {
									if ( isset( $JSONData['macAddress'] ) ) {
										$this->SetValue( 'MAC', $JSONData[ 'macAddress' ] );
									} else {
										$this->SetValue( 'MAC', '-' );
									}
								}
								if ( isset( $JSONData['uplink']['deviceId'] ) ) {									
									$this->Send('getDeviceName',$JSONData['uplink']['deviceId']);
								}
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
													$colSymbol=self::COLOR_YELLOW; //gelb
												} else {
													$colSymbol=self::COLOR_GREEN; //gruen
												}                                
											} else {
												$colSymbol=self::COLOR_RED; //rot
											}

											$this->MaintainVariable( 'Port_'.$port[ 'idx' ], $this->Translate( 'Port' ).' '.$port[ 'idx' ].($port['connector']== 'RJ45' ? '' : '-'.$port['connector']), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'ethernet'], $vpos++, $this->ReadPropertyBoolean("PortsAnzeigen") );
											$PortText='';
											if (isset($port[ 'speedMbps' ]) && $port[ 'state' ]== 'UP') {
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
													$colSymbol=self::COLOR_GREEN;
													$poe=$port[ 'poe' ]['standard'];
												}else {
													$colSymbol=self::COLOR_RED;
													$poe=$port[ 'poe' ]['state'];
												}
												$this->MaintainVariable( 'Port_'.$port[ 'idx' ].'POE', $this->Translate( 'Port' ).' '.$port[ 'idx' ].'-POE', 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'ethernet'], $vpos++, $this->ReadPropertyBoolean("PortsAnzeigen") );
												if ($this->ReadPropertyBoolean("PortsAnzeigen")) {
													$this->SetValue( 'Port_'.$port[ 'idx' ].'POE', $poe);
												}
											} else {
												$vpos++;
											}
											if ($this->ReadPropertyBoolean("PortsAnzeigen")) {
												$this->SetValue( 'Port_'.$port[ 'idx' ], $speed);
											}
											
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
											$this->MaintainVariable( 'Port_'.$vpos, $this->Translate( 'WLAN' ).' '.$radio[ 'frequencyGHz' ].' GHz', 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,'COLOR'=>$colSymbol,'ICON'=>'wifi'], $vpos, $this->ReadPropertyBoolean("RadiosAnzeigen") );
											if ($this->ReadPropertyBoolean("RadiosAnzeigen")) {
												$this->SetValue( 'Port_'.$vpos, $radioTxt );
											}											
										}
									}
								}
							}
						}
						$this->Send('getDeviceStats','');
						$this->Send('getConnectedClients','');
						//IPS_LogMessage('UNIFICL-'.$this->InstanceID,var_dump($array));						
						break;
					case "getDeviceName":
						$data=unserialize($data);
						$this->SendDebug('UnifiDV', $data, 0);
						$this->SetValue( 'UplinkDevice', $data);
						break;
					case "setPortCycle":
						$data=unserialize($data);
						$this->SendDebug('UnifiDV', 'PortCycle: '.$data, 0);
						break;
					case "setRestartDevice":
						$data=unserialize($data);
						$this->SendDebug('UnifiDV', 'RestartDevice: '.$data, 0);
						break;

				}			
			}
		}

		public function PowerCycle(int $port)
		{
			if ($this->HasActiveParent()) {
				$this->Send('setPortCycle',strval($port));
			}
		}

		public function RestartDevice()
		{
			if ($this->HasActiveParent()) {
				$this->Send('setRestartDevice','');
			}
		}

		public function GetConfigurationForm(){       
			if ($this->HasActiveParent()) {
				$this->Send('getDevices','');
			}	
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instance is active' );

			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'bold' => true, 'label' => $this->Translate('UniFi Device'));
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Set Timer to activate instance. The instance will then automatically collect data from the device at the specified interval.')); 
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );

			$Bufferdata = $this->GetBuffer("devices");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => $this->Translate('Please load the data first'), 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Device ID', 'options' => $arrayOptions );
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'PortsAnzeigen', 'width' => '220px', 'caption' => $this->Translate('Show Ports') );
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'RadiosAnzeigen', 'width' => '220px', 'caption' => $this->Translate('Show Radios') );
			$arrayElements[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'MACAnzeigen','width' => '220px', 'caption' => $this->Translate('Show MAC') );
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'IDAnzeigen', 'width' => '220px','caption' => $this->Translate('Show ID') );
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'ConnectedClientsAnzeigen', 'width' => '220px','caption' => $this->Translate('Show Connected Clients') );
			$arrayElements[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			$arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'Utilization', 'caption' => $this->Translate('Read utilization statistics (CPU + memory)') );

			$arrayActions = array();
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Devices'),'width' => '220px', 'onClick' => 'UNIFIDV_Send($id,"getDevices","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Data'), 'width' => '220px','onClick' => 'UNIFIDV_Send($id,"getDeviceData","");' );
			$arrayActions[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Statistics Data'),'width' => '220px', 'onClick' => 'UNIFIDV_Send($id,"getDeviceStats","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Restart'), 'width' => '220px','onClick' => 'UNIFIDV_Send($id,"setRestartDevice","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'label' => $this->Translate('Get Connected Clients'), 'width' => '220px','onClick' => 'UNIFIDV_Send($id,"getConnectedClients","");' );
			$arrayActions[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
	    }
	}