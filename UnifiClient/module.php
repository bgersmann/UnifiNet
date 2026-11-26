<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/UnifiTile.php';

class UnifiClient extends IPSModuleStrict
{
    use UnifiTile;

    private const COLOR_GREEN = 1692672;
    private const COLOR_RED = 16077123;

    public function GetCompatibleParents(): string
    {
        return json_encode([ 'type' => 'connect', 'moduleIDs' => [ '{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}' ] ]);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('ID', '');
        $this->RegisterPropertyInteger('Timer', 0);
		$this->RegisterPropertyBoolean("MACAnzeigen", 0);
		$this->RegisterPropertyBoolean("IDAnzeigen", 0);
        // Unofficial API (gateway with local user): activity and blocking
        $this->RegisterPropertyBoolean( 'privActivity', false );
        $this->RegisterPropertyBoolean( 'privBlock', false );
        // Blocked clients disappear from the official API, so the MAC is kept.
        $this->RegisterAttributeString( 'MAC', '' );
        $this->RegisterTimer('Collect Data', 0, "UNIFICL_Send(\$_IPS['TARGET'],'getClientData','');");
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
			$this->MaintainVariable( 'ClientName', $this->Translate( 'Client Name' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'ID', $this->Translate( 'Client ID' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("IDAnzeigen") );
			$this->MaintainVariable( 'ClientType', $this->Translate( 'Client Type' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'ClientIP', $this->Translate( 'Client IP' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			// The date/time presentation has no ICON parameter, so the icon is set on the object.
			// RegisterVariableInteger does not return the variable ID, hence GetIDForIdent afterwards.
			if (@$this->GetIDForIdent("ConnectedAt") == false) {
				$this->RegisterVariableInteger('ConnectedAt', $this->Translate( 'Connected At' ), [ 'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME],$vpos++);
				IPS_SetIcon($this->GetIDForIdent("ConnectedAt"),'circle-info');
			} else {
				$vpos++;
			}
			$this->MaintainVariable( 'Online', $this->Translate( 'Online' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'network-wired','OPTIONS'=> json_encode( [
				[ 'Caption'=> $this->Translate( 'Offline' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_RED, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> false ],
				[ 'Caption'=> $this->Translate( 'Online' ), 'ColorActive'=> true, 'ColorValue'=> self::COLOR_GREEN, 'ContentColorActive'=> false, 'ContentColorValue'=> -1, 'IconActive'=> false, 'IconValue'=> '', 'Value'=> true ]
			] )], $vpos++, 1 );
			$this->MaintainVariable( 'UplinkDevice', $this->Translate( 'Uplink Device' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, 1 );
			$this->MaintainVariable( 'MAC', $this->Translate( 'Client MAC' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("MACAnzeigen") );
			$activity = $this->ReadPropertyBoolean( 'privActivity' );
			$this->MaintainVariable( 'ActivityDown', $this->Translate( 'Activity Download' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 3, 'SUFFIX' => ' Mbit/s', 'ICON' => 'download' ], $vpos++, $activity );
			$this->MaintainVariable( 'ActivityUp', $this->Translate( 'Activity Upload' ), 2, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 3, 'SUFFIX' => ' Mbit/s', 'ICON' => 'upload' ], $vpos++, $activity );
			$this->MaintainVariable( 'Blocked', $this->Translate( 'Blocked' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH, 'ICON_TRUE' => 'ban', 'USAGE_TYPE' => 2 ], $vpos++, $this->ReadPropertyBoolean( 'privBlock' ) );
			if ( $this->ReadPropertyBoolean( 'privBlock' ) ) {
				$this->EnableAction( 'Blocked' );
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
				$this->Send('getClientData','');			
			}
		}

		public function Send(string $api, string $param1): void
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
					case "getClients":
						$deviceData=json_encode(unserialize($data));
						$this->UpdateFormField("ID", "options", $deviceData);
						$this->SetBuffer("clients", $deviceData);
						break;
					case "getClientData":
						$array=json_decode(unserialize($data),true);
						$this->SendDebug("UnifiCL", unserialize($data), 0);
						# $array = json_decode($data['data'],true);
						if ( isset( $array[ 'statusCode' ] ) ) {
							if ($array[ 'statusCode' ]== 404) {
								// instance inactive
								$this->SendDebug("UnifiCL", "Device Offline: " . json_encode($array), 0);
								$this->SetValue( 'Online', false );
								$this->SetValue( 'UplinkDevice', '-');
								$this->TileOffline();
							} else {
								// instance inactive
								$this->SendDebug("UnifiCL", "Request failed: " . json_encode($array), 0);
								$this->SetValue( 'Online', false );
								$this->SetValue( 'UplinkDevice', '-');
								$this->TileOffline();
								$this->SetStatus( $array[ 'statusCode' ] );
							}
						}	

						if ( is_array( $array ) && isset( $array ) ) {
							if ( isset( $array[ 'statusCode' ] ) ) {
								$this->SetValue( 'Online', false );
							} else {
								$this->TileUpdate( [
									'name' => (string) $array[ 'name' ],
									'type' => (string) ( $array[ 'type' ] ?? '' ),
									'ip' => (string) ( $array[ 'ipAddress' ] ?? '' ),
									'mac' => (string) ( $array[ 'macAddress' ] ?? '' ),
									'access' => (string) ( $array[ 'access' ][ 'type' ] ?? '' ),
									'connectedAt' => strtotime( (string) ( $array[ 'connectedAt' ] ?? '' ) ) ?: null,
									'online' => true,
									'uplinkId' => $this->TileDeviceInstance( (string) ( $array[ 'uplinkDeviceId' ] ?? '' ) ),
									'uplink' => isset( $array[ 'uplinkDeviceId' ] ) ? ( $this->TileReadState()[ 'uplink' ] ?? '' ) : ''
								] );
								$this->SetValue( 'ClientName', $array[ 'name' ] );
								if ($this->ReadPropertyBoolean("IDAnzeigen")) {
									$this->SetValue( 'ID', $array[ 'id' ] );
								}								
								$this->SetValue( 'ClientType', $array[ 'type' ] );
								if ( isset( $array[ 'ipAddress' ] ) ) {
									$this->SetValue( 'ClientIP', $array[ 'ipAddress' ] );
									$this->SetSummary($array[ 'ipAddress' ]);
								}
								$this->SetValue( 'ConnectedAt', strtotime( $array[ 'connectedAt' ] ) );
								$this->SetValue( 'Online', true );
								if ( isset( $array['uplinkDeviceId'] ) ) {
									$this->Send('getDeviceName',$array['uplinkDeviceId']);
								}
								if ($this->ReadPropertyBoolean("MACAnzeigen")) {
									$this->SetValue( 'MAC', $array[ 'macAddress' ] );
								}
								if ( isset( $array[ 'macAddress' ] ) ) {
									$this->WriteAttributeString( 'MAC', (string) $array[ 'macAddress' ] );
								}
							}
						}
						// Also for offline clients: a blocked client is offline.
						$this->UpdatePrivateClient();
						break;
					case "getDeviceName":
						$data=unserialize($data);
						$this->SendDebug("UnifiCL", $data, 0);
						$this->SetValue( 'UplinkDevice', $data);
						$this->TileUpdate( [ 'uplink' => (string) $data ] );
						break;

				}
			}			
		}

		public function RequestAction( string $Ident, mixed $Value ): void
		{
			switch ( $Ident ) {
				case 'Blocked':
					if ( !$this->SetBlocked( (bool) $Value ) ) {
						echo $this->Translate( 'The client could not be changed. Details are in the debug output of the gateway.' );
					}
					break;
				default:
					throw new Exception( 'Invalid Ident' );
			}
		}

		/** Blocks or unblocks the client in the network (unofficial API). */
		public function SetBlocked( bool $blocked ): bool
		{
			$ok = (bool) $this->PrivateCall( 'privSetClientBlocked', [ 'blocked' => $blocked ] );
			if ( $ok && $this->ReadPropertyBoolean( 'privBlock' ) ) {
				$this->SetValue( 'Blocked', $blocked );
				$this->TileUpdate( [ 'blocked' => $blocked ] );
			} elseif ( $this->ReadPropertyBoolean( 'privBlock' ) ) {
				// The tile already shows the new state: push the real one back.
				$this->TileUpdate( [ 'blocked' => (bool) $this->GetValue( 'Blocked' ) ] );
			}
			return $ok;
		}

		/** Request to the unofficial API of the gateway; the MAC of this client is added. */
		private function PrivateCall( string $api, array $param ): mixed
		{
			$mac = $this->ReadAttributeString( 'MAC' );
			if ( $mac === '' || !$this->HasActiveParent() ) {
				$this->SendDebug( 'UnifiCL', 'Unofficial API: MAC unknown or gateway not active', 0 );
				return null;
			}
			$param[ 'mac' ] = $mac;
			$data = $this->SendDataToParent( json_encode( [ 'DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}', 'Api' => $api, 'InstanceID' => $this->InstanceID, 'Param1' => json_encode( $param ) ] ) );
			return $data === '' ? null : unserialize( $data );
		}

		private function UpdatePrivateClient(): void
		{
			$activity = $this->ReadPropertyBoolean( 'privActivity' );
			$block = $this->ReadPropertyBoolean( 'privBlock' );
			if ( !$activity && !$block ) {
				$state = $this->TileReadState();
				if ( isset( $state[ 'activity' ] ) || isset( $state[ 'blocked' ] ) ) {
					$this->TileUpdate( [ 'activity' => null, 'blocked' => null ] );
				}
				return;
			}
			$result = json_decode( (string) $this->PrivateCall( 'privGetClient', [] ), true );
			if ( !is_array( $result ) || !( $result[ 'ok' ] ?? false ) ) {
				$this->SendDebug( 'UnifiCL', 'Unofficial API: no client data (enabled in the gateway?)', 0 );
				return;
			}
			if ( $activity ) {
				// tx = from the network to the client (download), rx = from the client (upload); bytes/s -> Mbit/s
				$this->SetValue( 'ActivityDown', round( $result[ 'tx' ] * 8 / 1000000, 3 ) );
				$this->SetValue( 'ActivityUp', round( $result[ 'rx' ] * 8 / 1000000, 3 ) );
			}
			if ( $block ) {
				$this->SetValue( 'Blocked', (bool) $result[ 'blocked' ] );
			}
			$this->TileUpdate( [
				'activity' => $activity ? [ 'down' => $result[ 'tx' ] * 8 / 1000000, 'up' => $result[ 'rx' ] * 8 / 1000000 ] : null,
				'blocked' => $block ? (bool) $result[ 'blocked' ] : null
			] );
		}

		public function GetConfigurationForm(): string {
			if ($this->HasActiveParent()) {
				$this->Send("getClients",'');
			}			
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instance is active' );
			$arrayStatus[] = array( 'code' => 400, 'icon' => 'error', 'caption' => 'Instance is faulty: Bad Request' );
			$arrayStatus[] = array( 'code' => 401, 'icon' => 'error', 'caption' => 'Instance is faulty: Unauthorized' );
			$arrayStatus[] = array( 'code' => 403, 'icon' => 'error', 'caption' => 'Instance is faulty: Forbidden' );
			$arrayStatus[] = array( 'code' => 429, 'icon' => 'error', 'caption' => 'Instance is faulty: Rate Limit' );
			$arrayStatus[] = array( 'code' => 500, 'icon' => 'error', 'caption' => 'Instance is faulty: Server Error' );
			$arrayStatus[] = array( 'code' => 502, 'icon' => 'error', 'caption' => 'Instance is faulty: Bad Gateway' );

			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'bold' => true, 'label' => $this->Translate('UniFi Client') );
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Set Timer to activate instance. The instance will then automatically collect data from the device at the specified interval.')); 
			
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );
			$Bufferdata = $this->GetBuffer("clients");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => $this->Translate('Please load the data first'), 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Client ID', 'options' => $arrayOptions );
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'MACAnzeigen', 'caption' => $this->Translate('Show MAC') );
			$arrayOptions[] = array( 'type' => 'CheckBox', 'name' => 'IDAnzeigen', 'caption' => $this->Translate('Show ID') );
			$arrayElements[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			$arrayElements[] = array( 'type' => 'ExpansionPanel', 'caption' => $this->Translate( 'Unofficial API' ), 'expanded' => $this->ReadPropertyBoolean( 'privActivity' ) || $this->ReadPropertyBoolean( 'privBlock' ), 'items' => [
				[ 'type' => 'Label', 'label' => $this->Translate( 'These functions use the unofficial UniFi API and require it to be enabled in the gateway (local user + password).' ) ],
				[ 'type' => 'CheckBox', 'name' => 'privActivity', 'caption' => $this->Translate( 'Read activity (current download/upload)' ) ],
				[ 'type' => 'CheckBox', 'name' => 'privBlock', 'caption' => $this->Translate( 'Switchable variable to block/unblock the client' ) ]
			] );

			$arrayActions = array();
			unset($arrayOptions);
			$arrayOptions[] = array( 'type' => 'Button', 'width' => '220px','label' => $this->Translate('Get Clients'), 'onClick' => 'UNIFICL_Send($id,"getClients","");' );
			$arrayOptions[] = array( 'type' => 'Button', 'width' => '220px','label' => $this->Translate('Get Data'), 'onClick' => 'UNIFICL_Send($id,"getClientData","");' );
			$arrayActions[] = array( 'type' => 'RowLayout',  'items' => $arrayOptions );
			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
			
    	}

		private function TileKind(): string
		{
			return 'client';
		}

		private function TileRefresh(): void
		{
			$this->Send('getClientData','');
		}

		/** An offline client is unknown to the API: name and IP stay, the rest is reset. */
		private function TileOffline(): void
		{
			$values = [ 'online' => false, 'uplink' => '', 'uplinkId' => 0, 'offlineAt' => IPS_GetVariable( $this->GetIDForIdent( 'Online' ) )[ 'VariableChanged' ] ];
			if ( !isset( $this->TileReadState()[ 'name' ] ) ) {
				$values[ 'name' ] = $this->GetValue( 'ClientName' ) !== '' ? $this->GetValue( 'ClientName' ) : IPS_GetName( $this->InstanceID );
				$values[ 'ip' ] = $this->GetValue( 'ClientIP' );
			}
			$this->TileUpdate( $values );
		}

		/** Instance of the uplink device, so that the tile can open it. */
		private function TileDeviceInstance( string $deviceID ): int
		{
			if ( $deviceID === '' ) {
				return 0;
			}
			$connection = IPS_GetInstance( $this->InstanceID )[ 'ConnectionID' ];
			foreach ( IPS_GetInstanceListByModuleID( '{19A9D2AF-BD00-461A-58E1-7BF7A0CA19A6}' ) as $instanceID ) {
				if ( IPS_GetProperty( $instanceID, 'ID' ) === $deviceID && IPS_GetInstance( $instanceID )[ 'ConnectionID' ] === $connection ) {
					return $instanceID;
				}
			}
			return 0;
		}
	}