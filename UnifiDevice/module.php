<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/UnifiTile.php';

	class UnifiDevice extends IPSModuleStrict
	{
    use UnifiTile;

        private const COLOR_YELLOW = 16776960;
        private const COLOR_GREEN = 1692672;
        private const COLOR_RED = 16077123;
        private const COLOR_UPDATE = 16711680;
        /** Failed requests in a row until the device is shown as offline. */
        private const FAIL_LIMIT = 3;
		public function GetCompatibleParents(): string
		{
			return json_encode([ 'type' => 'connect', 'moduleIDs' => [ '{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}' ] ]);
		}

		public function Create(): void
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString( 'ID', '' );
        	$this->RegisterPropertyInteger( 'Timer', '0' );
        	$this->RegisterTimer( 'Collect Data', 0, "UNIFIDV_Send(\$_IPS['TARGET'],'getDeviceData','');" );
			$this->RegisterPropertyBoolean("MACAnzeigen", 0);
			$this->RegisterPropertyBoolean("ConnectedClientsAnzeigen", 0);
			$this->RegisterPropertyBoolean("IDAnzeigen", 0);
			$this->RegisterPropertyBoolean("PortsAnzeigen", 0);
			$this->RegisterPropertyBoolean("RadiosAnzeigen", 0);
			$this->RegisterPropertyBoolean("Utilization", 0);
			// Unofficial API (gateway with local user): switchable ports and PoE
			$this->RegisterPropertyBoolean( 'privPortSwitch', false );
			$this->RegisterPropertyBoolean( 'privPoeControl', false );
			$this->RegisterAttributeString( 'MAC', '' );
			$this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML') ? constant('INSTANCE_VISUALIZATION_TYPE_HTML') : 1);
		}

		public function Destroy(): void
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges(): void
		{
			//Never delete this line!
			parent::ApplyChanges();
			$this->SetVisualizationType(defined('INSTANCE_VISUALIZATION_TYPE_HTML') ? constant('INSTANCE_VISUALIZATION_TYPE_HTML') : 1);
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

			// The duration presentation has no ICON parameter, so the icon is set on the object.
			// RegisterVariableInteger does not return the variable ID, hence GetIDForIdent afterwards.
			if (@$this->GetIDForIdent("UptimeSec") == false) {
				$this->RegisterVariableInteger('UptimeSec', $this->Translate( 'Uptime' ), [ 'PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'FORMAT'=> 2],$vpos++);
				IPS_SetIcon($this->GetIDForIdent("UptimeSec"),'circle-info');
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
			// The port variables are created with the data; switched-off options remove them here.
			if ( !$this->ReadPropertyBoolean( 'privPortSwitch' ) ) {
				$this->RemovePrivateVariables( 'PortEnabled_' );
			}
			if ( !$this->ReadPropertyBoolean( 'privPoeControl' ) ) {
				$this->RemovePrivateVariables( 'PortPoE_' );
			}



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

		public function Send(string $api, string $param1): void
		{
			if ( !$this->HasActiveParent() && $api === 'getDeviceData' ) {
				// The gateway is faulty: the device cannot be reached either.
				$this->RequestFailed( 'gateway not active' );
				return;
			}
			if ($this->HasActiveParent()) {
				$data=$this->SendDataToParent(json_encode(['DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
					'Api' => $api,
					'InstanceID' => $this->InstanceID,
					'Param1' => $param1
					]));
				if (!$data) {
					$this->SendDebug("UnifiDV", "Send Data Error: " . $api, 0);
					if ( $api === 'getDeviceData' ) {
						$this->RequestFailed( 'no answer from the gateway' );
					}
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
						if ( isset( $JSONData['connectedClients'] ) ) {
							$this->TileUpdate( [ 'clients' => $JSONData['connectedClients'] ] );
						}
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
							$this->TileUpdate( $this->TileStats( $JSONData ) );
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
						if ( ( $JSONData[ 'statusCode' ] ?? 0 ) == 404 ) {
							// The device is no longer known to the controller.
							$this->RequestSucceeded();
							$this->SetOffline();
						} elseif ( !is_array( $JSONData ) || isset( $JSONData[ 'statusCode' ] ) || !isset( $JSONData[ 'id' ], $JSONData[ 'name' ] ) ) {
							// Error of the controller or an incomplete answer, e.g. during an update.
							$this->RequestFailed( substr( (string) unserialize( $data ), 0, 300 ) );
							break;
						} else {
							$this->RequestSucceeded();
							{
								$this->TileUpdate( $this->TileDevice( $JSONData ) );
								$this->SetValue( 'DeviceName', $JSONData[ 'name' ] );
								if ($this->ReadPropertyBoolean("IDAnzeigen")) {
									$this->SetValue( 'ID', $JSONData[ 'id' ] );
								}
								$this->SetValue( 'DeviceModel', (string) ( $JSONData[ 'model' ] ?? '' ) );
								$this->SetValue( 'DeviceIP', (string) ( $JSONData[ 'ipAddress' ] ?? '' ) );
								$this->SetSummary( (string) ( $JSONData[ 'ipAddress' ] ?? '' ) );
								$this->SetValue( 'Firmware', (string) ( $JSONData[ 'firmwareVersion' ] ?? '' ) );
								$this->SetValue( 'FirmwareUpdate', (bool) ( $JSONData[ 'firmwareUpdatable' ] ?? false ) );
								$this->SetValue( 'Online', ( $JSONData[ 'state' ] ?? '' ) == 'ONLINE' );
								if ( isset( $JSONData[ 'macAddress' ] ) ) {
									$this->WriteAttributeString( 'MAC', (string) $JSONData[ 'macAddress' ] );
								}
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
						$this->UpdatePrivatePorts();
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

		public function RequestAction( string $Ident, mixed $Value ): void
		{
			if ( preg_match( '/^PortEnabled_(\d+)$/', $Ident, $m ) ) {
				if ( !$this->SetPortEnabled( (int) $m[ 1 ], (bool) $Value ) ) {
					echo $this->Translate( 'The port could not be changed. Details are in the debug output of the gateway.' );
				}
				return;
			}
			if ( preg_match( '/^PortPoE_(\d+)$/', $Ident, $m ) ) {
				if ( !$this->SetPoeMode( (int) $m[ 1 ], (string) $Value ) ) {
					echo $this->Translate( 'The PoE mode could not be changed. Details are in the debug output of the gateway.' );
				}
				return;
			}
			throw new Exception( 'Invalid Ident' );
		}

		/** Enables or disables a switch port (unofficial API). The uplink port is never disabled. */
		public function SetPortEnabled( int $port, bool $enabled ): bool
		{
			$ok = (bool) $this->PrivateCall( 'privSetPortEnabled', [ 'port' => $port, 'enabled' => $enabled ] );
			if ( $ok ) {
				if ( @$this->GetIDForIdent( 'PortEnabled_'.$port ) ) {
					$this->SetValue( 'PortEnabled_'.$port, $enabled );
				}
				$priv = $this->TileReadState()[ 'portsPriv' ] ?? [];
				if ( isset( $priv[ $port ] ) ) {
					$priv[ $port ][ 'enabled' ] = $enabled;
					$this->TileUpdate( [ 'portsPriv' => (object) $priv ] );
				}
			}
			return $ok;
		}

		/** Sets the PoE mode of a port: auto, off, pasv24 or passthrough (unofficial API). */
		public function SetPoeMode( int $port, string $mode ): bool
		{
			$ok = (bool) $this->PrivateCall( 'privSetPoeMode', [ 'port' => $port, 'mode' => $mode ] );
			if ( $ok ) {
				if ( @$this->GetIDForIdent( 'PortPoE_'.$port ) ) {
					$this->SetValue( 'PortPoE_'.$port, $mode );
				}
				$priv = $this->TileReadState()[ 'portsPriv' ] ?? [];
				if ( isset( $priv[ $port ] ) ) {
					$priv[ $port ][ 'poeMode' ] = $mode;
					$this->TileUpdate( [ 'portsPriv' => (object) $priv ] );
				}
			}
			return $ok;
		}

		/** Request to the unofficial API of the gateway; the MAC of this device is added. */
		private function PrivateCall( string $api, array $param ): mixed
		{
			$mac = $this->ReadAttributeString( 'MAC' );
			if ( $mac === '' || !$this->HasActiveParent() ) {
				return null;
			}
			$param[ 'mac' ] = $mac;
			$data = $this->SendDataToParent( json_encode( [ 'DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}', 'Api' => $api, 'InstanceID' => $this->InstanceID, 'Param1' => json_encode( $param ) ] ) );
			return $data === '' ? null : unserialize( $data );
		}

		/** Switchable admin state and PoE mode per port, read with the unofficial API. */
		private function UpdatePrivatePorts(): void
		{
			$portSwitch = $this->ReadPropertyBoolean( 'privPortSwitch' );
			$poeControl = $this->ReadPropertyBoolean( 'privPoeControl' );
			if ( !$portSwitch && !$poeControl ) {
				if ( isset( $this->TileReadState()[ 'portsPriv' ] ) ) {
					$this->TileUpdate( [ 'portsPriv' => null ] );
				}
				return;
			}
			$result = json_decode( (string) $this->PrivateCall( 'privGetDevicePorts', [] ), true );
			if ( !is_array( $result ) || !( $result[ 'ok' ] ?? false ) ) {
				$this->SendDebug( 'UnifiDV', 'Unofficial API: no port data (enabled in the gateway?)', 0 );
				return;
			}
			$tile = [];
			foreach ( $result[ 'ports' ] as $port ) {
				$idx = (int) $port[ 'idx' ];
				// The tile shows the admin state and the PoE mode in the port details.
				if ( $portSwitch ) {
					$tile[ $idx ][ 'enabled' ] = (bool) $port[ 'enabled' ];
				}
				if ( $poeControl && $port[ 'poe' ] ) {
					$tile[ $idx ][ 'poeMode' ] = (string) $port[ 'poeMode' ];
				}
				$label = $this->Translate( 'Port' ).' '.$idx;
				// The uplink port gets no switch: disabling it would cut the device off.
				if ( $portSwitch && !$port[ 'uplink' ] ) {
					$this->MaintainVariable( 'PortEnabled_'.$idx, $label.' '.$this->Translate( 'enabled' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH, 'ICON_TRUE' => 'ethernet', 'USAGE_TYPE' => 0 ], 1500 + $idx * 2, true );
					$this->EnableAction( 'PortEnabled_'.$idx );
					$this->SetValue( 'PortEnabled_'.$idx, (bool) $port[ 'enabled' ] );
				}
				if ( $poeControl && $port[ 'poe' ] ) {
					$options = [
						[ 'Value' => 'auto', 'Caption' => $this->Translate( 'Auto' ), 'IconActive' => false, 'IconValue' => '', 'Color' => -1 ],
						[ 'Value' => 'off', 'Caption' => $this->Translate( 'Off' ), 'IconActive' => false, 'IconValue' => '', 'Color' => -1 ]
					];
					if ( $port[ 'poePasv24' ] || $port[ 'poeMode' ] === 'pasv24' ) {
						$options[] = [ 'Value' => 'pasv24', 'Caption' => $this->Translate( '24V passive' ), 'IconActive' => false, 'IconValue' => '', 'Color' => -1 ];
					}
					if ( $port[ 'poePassthrough' ] || $port[ 'poeMode' ] === 'passthrough' ) {
						$options[] = [ 'Value' => 'passthrough', 'Caption' => $this->Translate( 'Passthrough' ), 'IconActive' => false, 'IconValue' => '', 'Color' => -1 ];
					}
					$this->MaintainVariable( 'PortPoE_'.$idx, $label.' PoE', 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'plug', 'OPTIONS' => json_encode( $options ) ], 1500 + $idx * 2 + 1, true );
					$this->EnableAction( 'PortPoE_'.$idx );
					$this->SetValue( 'PortPoE_'.$idx, $port[ 'poeMode' ] );
				}
			}
			$this->TileUpdate( [ 'portsPriv' => (object) $tile ] );
		}

		private function RemovePrivateVariables( string $prefix ): void
		{
			foreach ( IPS_GetChildrenIDs( $this->InstanceID ) as $childID ) {
				$ident = IPS_GetObject( $childID )[ 'ObjectIdent' ];
				if ( str_starts_with( $ident, $prefix ) ) {
					$this->UnregisterVariable( $ident );
				}
			}
		}

		public function PowerCycle(int $port): void
		{
			if ($this->HasActiveParent()) {
				$this->Send('setPortCycle',strval($port));
			}
		}

		public function RestartDevice(): void
		{
			if ($this->HasActiveParent()) {
				$this->Send('setRestartDevice','');
			}
		}

		public function GetConfigurationForm(): string {
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
			$arrayElements[] = array( 'type' => 'ExpansionPanel', 'caption' => $this->Translate( 'Unofficial API' ), 'expanded' => $this->ReadPropertyBoolean( 'privPortSwitch' ) || $this->ReadPropertyBoolean( 'privPoeControl' ), 'items' => [
				[ 'type' => 'Label', 'label' => $this->Translate( 'These functions use the unofficial UniFi API and require it to be enabled in the gateway (local user + password).' ) ],
				[ 'type' => 'CheckBox', 'name' => 'privPortSwitch', 'caption' => $this->Translate( 'Switchable variable per port (enable/disable port)' ) ],
				[ 'type' => 'CheckBox', 'name' => 'privPoeControl', 'caption' => $this->Translate( 'Selectable PoE mode per PoE port' ) ]
			] );

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

		/**
		 * A single failed or incomplete answer of the controller (timeout, restart, update) does not
		 * change the state. Only after 3 failures in a row the device is shown as offline.
		 */
		private function RequestFailed( string $reason ): void
		{
			$count = (int) $this->GetBuffer( 'FailCount' ) + 1;
			$this->SetBuffer( 'FailCount', (string) $count );
			$this->SendDebug( 'UnifiDV', 'Request failed ('.$count.'/'.self::FAIL_LIMIT.'): '.$reason, 0 );
			if ( $count >= self::FAIL_LIMIT ) {
				$this->SetOffline();
			}
		}

		private function RequestSucceeded(): void
		{
			$this->SetBuffer( 'FailCount', '0' );
		}

		private function SetOffline(): void
		{
			$this->SetValue( 'Online', false );
			$this->TileUpdate( [ 'online' => false ] );
		}

		private function TileKind(): string
		{
			return 'device';
		}

		private function TileRefresh(): void
		{
			$this->Send('getDeviceData','');
		}

		/** Displayed fields of the device data. The API has no explicit type, so the role is derived. */
		private function TileDevice( array $device ): array
		{
			$ports = [];
			foreach ( $device[ 'interfaces' ][ 'ports' ] ?? [] as $port ) {
				$ports[] = [
					'idx' => (int) $port[ 'idx' ],
					'up' => ( $port[ 'state' ] ?? '' ) === 'UP',
					'conn' => (string) ( $port[ 'connector' ] ?? 'RJ45' ),
					'speed' => (int) ( $port[ 'speedMbps' ] ?? 0 ),
					'poe' => (string) ( $port[ 'poe' ][ 'standard' ] ?? '' ),
					'poeOn' => ( $port[ 'poe' ][ 'state' ] ?? '' ) === 'UP'
				];
			}
			// The retries come with the statistics and must survive this update.
			$retries = [];
			foreach ( $this->TileReadState()[ 'radios' ] ?? [] as $radio ) {
				$retries[ (string) (float) $radio[ 'ghz' ] ] = $radio[ 'retries' ] ?? null;
			}
			$radios = [];
			foreach ( $device[ 'interfaces' ][ 'radios' ] ?? [] as $radio ) {
				$ghz = (float) ( $radio[ 'frequencyGHz' ] ?? 0 );
				$radios[] = [
					'ghz' => $ghz,
					'standard' => (string) ( $radio[ 'wlanStandard' ] ?? '' ),
					'channel' => $radio[ 'channel' ] ?? null,
					'width' => $radio[ 'channelWidthMHz' ] ?? null,
					'retries' => $retries[ (string) $ghz ] ?? null
				];
			}
			if ( !isset( $device[ 'uplink' ][ 'deviceId' ] ) ) {
				$role = 'gateway';
			} elseif ( isset( $device[ 'features' ][ 'accessPoint' ] ) ) {
				$role = 'ap';
			} elseif ( count( $ports ) > 0 ) {
				$role = 'switch';
			} else {
				$role = 'device';
			}
			return [
				'name' => (string) ( $device[ 'name' ] ?? '' ),
				'model' => (string) ( $device[ 'model' ] ?? '' ),
				'ip' => (string) ( $device[ 'ipAddress' ] ?? '' ),
				'online' => ( $device[ 'state' ] ?? '' ) === 'ONLINE',
				'update' => (bool) ( $device[ 'firmwareUpdatable' ] ?? false ),
				'role' => $role,
				'ports' => $ports,
				'radios' => $radios
			];
		}

		/** Displayed fields of the device statistics. */
		private function TileStats( array $stats ): array
		{
			$radios = $this->TileReadState()[ 'radios' ] ?? [];
			foreach ( $stats[ 'interfaces' ][ 'radios' ] ?? [] as $radioStats ) {
				foreach ( $radios as &$radio ) {
					if ( (float) $radio[ 'ghz' ] === (float) ( $radioStats[ 'frequencyGHz' ] ?? -1 ) ) {
						$radio[ 'retries' ] = $radioStats[ 'txRetriesPct' ] ?? null;
					}
				}
				unset( $radio );
			}
			return [
				'uptime' => $stats[ 'uptimeSec' ] ?? null,
				'cpu' => $stats[ 'cpuUtilizationPct' ] ?? null,
				'mem' => $stats[ 'memoryUtilizationPct' ] ?? null,
				'tx' => isset( $stats[ 'uplink' ][ 'txRateBps' ] ) ? $stats[ 'uplink' ][ 'txRateBps' ] / 1000000 : null,
				'rx' => isset( $stats[ 'uplink' ][ 'rxRateBps' ] ) ? $stats[ 'uplink' ][ 'rxRateBps' ] / 1000000 : null,
				'radios' => $radios
			];
		}
	}