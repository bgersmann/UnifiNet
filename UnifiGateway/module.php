<?php

declare( strict_types = 1 );

require_once __DIR__ . '/../libs/UnifiTile.php';

class UnifiGateway extends IPSModuleStrict
{
    use UnifiTile;

private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 15;
    private const HIERARCHY_ROOT_ID = 'site';

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyString( 'ServerAddress', '192.168.178.1' );
        $this->RegisterPropertyString( 'APIKey', '' );
        $this->RegisterPropertyString( 'Site', 'default' );
        $this->RegisterPropertyBoolean('applicationVersion', 0);
        $this->RegisterPropertyInteger( 'VersionTimer', 3600 );
        $this->RegisterPropertyBoolean('networkHierarchy', 0);
        $this->RegisterPropertyInteger( 'HierarchyTimer', 3600 );
        $this->RegisterPropertyBoolean('hierarchyClients', 0);
        $this->RegisterTimer( 'Update Version', 0, "UNIFIGW_UpdateApplicationVersion(\$_IPS['TARGET']);" );
        $this->RegisterTimer( 'Update Hierarchy', 0, "UNIFIGW_UpdateNetworkHierarchy(\$_IPS['TARGET']);" );
        $this->RegisterPropertyInteger( 'TileTimer', 60 );
        $this->RegisterTimer( 'Update Tile', 0, "UNIFIGW_UpdateTile(\$_IPS['TARGET']);" );
        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML);
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
        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML);
        $vpos = 100;
$APIKey = $this->ReadPropertyString( 'APIKey' );
        $this->MaintainVariable( 'applicationVersion', $this->Translate( 'Application Version' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'circle-info'], $vpos++, $this->ReadPropertyBoolean("applicationVersion") );
        $this->MaintainVariable( 'networkHierarchy', $this->Translate( 'Network Hierarchy' ), 3, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'USAGE_TYPE'=> 0 ,'ICON'=> 'sitemap'], $vpos++, $this->ReadPropertyBoolean("networkHierarchy") );
        $active = !empty($APIKey);
        $this->SetTimerInterval( 'Update Version', ($active && $this->ReadPropertyBoolean("applicationVersion")) ? $this->ReadPropertyInteger( 'VersionTimer' ) * 1000 : 0 );
        $this->SetTimerInterval( 'Update Hierarchy', ($active && $this->ReadPropertyBoolean("networkHierarchy")) ? $this->ReadPropertyInteger( 'HierarchyTimer' ) * 1000 : 0 );
        $this->SetTimerInterval( 'Update Tile', $active ? $this->ReadPropertyInteger( 'TileTimer' ) * 1000 : 0 );
if (empty($APIKey))
		{
		    // instance inactive
			$this->SetStatus( 104 );
		} else {
		    // instance active
			$this->SetStatus( 102 );
            $this->SetSummary($this->ReadPropertyString("ServerAddress"));
            // During kernel start the timers take over; no requests before the system is ready.
            if ( IPS_GetKernelRunlevel() === KR_READY ) {
                $this->UpdateApplicationVersion();
                $this->UpdateNetworkHierarchy();
            }
		}
    }

    public function RequestAction( string $Ident, mixed $Value ): void
    {
        switch ($Ident) {
            case "applicationVersionChanged":
                $this->UpdateFormField( 'VersionTimer', 'enabled', (bool) $Value );
                break;
            case "networkHierarchyChanged":
                $this->UpdateFormField( 'HierarchyTimer', 'enabled', (bool) $Value );
                $this->UpdateFormField( 'hierarchyClients', 'enabled', (bool) $Value );
                break;
            default:
                throw new Exception( 'Invalid Ident' );
        }
    }

    public function UpdateApplicationVersion(): void
    {
        if ($this->ReadPropertyBoolean("applicationVersion")) {
            $this->storeApplicationVersion($this->getNetworkVersion());
        }
    }

    public function UpdateNetworkHierarchy(): void
    {
        if (!$this->ReadPropertyBoolean("networkHierarchy") || empty($this->ReadPropertyString( 'APIKey' ))) {
            return;
        }
        $json = $this->buildNetworkHierarchy();
        // Keep the last valid document if the data is incomplete: the tree must not lose devices.
        if ($json === '') {
            $this->SendDebug("UnifiGW", "Network hierarchy not updated.", 0);
            return;
        }
        $this->SetValue( 'networkHierarchy', $json );
        $this->SetStatus( 102 );
    }

    /** Updates the overview of the site in the tile of this instance. */
    public function UpdateTile(): void
    {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $response = $this->getApiData( '/'.$siteID.'/devices?limit=200' );
        if ( !isset( $response[ 'data' ] ) || !is_array( $response[ 'data' ] ) ) {
            $this->TileUpdate( [ 'error' => true ] );
            return;
        }
        $devices = [];
        foreach ( $response[ 'data' ] as $device ) {
            $devices[] = [
                'name' => (string) ( $device[ 'name' ] ?? '' ),
                'online' => ( $device[ 'state' ] ?? '' ) === 'ONLINE',
                'update' => (bool) ( $device[ 'firmwareUpdatable' ] ?? false ),
                'id' => $this->getInstanceIDForGuid( (string) ( $device[ 'id' ] ?? '' ), '{19A9D2AF-BD00-461A-58E1-7BF7A0CA19A6}' )
            ];
        }
        usort( $devices, function ( $a, $b ) {
            return strcmp( $a[ 'name' ], $b[ 'name' ] );
        } );
        $clients = $this->getApiData( '/'.$siteID.'/clients?limit=1' );
        $wans = $this->getSiteWans( $site );
        $this->TileUpdate( [
            'error' => false,
            'address' => $this->ReadPropertyString( 'ServerAddress' ),
            'version' => $this->getNetworkVersion(),
            'clients' => (int) ( $clients[ 'totalCount' ] ?? 0 ),
            'devices' => $devices,
            'wans' => array_column( $wans[ 'data' ] ?? [], 'name' )
        ] );
    }

    /** Number of connected clients with an IPv4 address in the given subnet (a.b.c.d/prefix). */
    private function getClientCountInSubnet( string $subnet ): int
    {
        [ $network, $prefix ] = array_pad( explode( '/', $subnet ), 2, '' );
        $networkLong = ip2long( $network );
        if ( $networkLong === false || !is_numeric( $prefix ) ) {
            return -1;
        }
        $mask = (int) $prefix === 0 ? 0 : ( ~0 << ( 32 - (int) $prefix ) ) & 0xFFFFFFFF;
        $siteID = $this->getSiteID( $this->ReadPropertyString( 'Site' ) );
        $response = $this->getApiData( '/'.$siteID.'/clients?limit=500' );
        if ( !isset( $response[ 'data' ] ) || !is_array( $response[ 'data' ] ) ) {
            return -1;
        }
        $count = 0;
        foreach ( $response[ 'data' ] as $client ) {
            $ip = ip2long( (string) ( $client[ 'ipAddress' ] ?? '' ) );
            if ( $ip !== false && ( $ip & $mask ) === ( $networkLong & $mask ) ) {
                $count++;
            }
        }
        return $count;
    }

    private function TileKind(): string
    {
        return 'site';
    }

    private function TileRefresh(): void
    {
        $this->UpdateTile();
    }

    /** The WLAN passphrase must not reach the debug output. */
    private function maskSecrets( string $json ): string
    {
        return (string) preg_replace( '/("passphrase"\s*:\s*")(?:[^"\\\\]|\\\\.)*"/', '$1***"', $json );
    }

    private function storeApplicationVersion( string $version ): void
{
        if ($version !== '' && $version !== 'notfound') {
            $this->SetValue( 'applicationVersion', $version );
        }
    }

    // Tree document for EChartsTree: de.burki24.symconecharts.tree, formatVersion 1.0.
    private function buildNetworkHierarchy(): string
    {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = '';
        $siteName = $site;
        $JSONData = $this->getApiData();
        foreach ( $JSONData[ 'data' ] ?? [] as $item ) {
            if ( $item[ 'internalReference' ] == $site ) {
                $siteID = $item[ 'id' ];
                $siteName = $item[ 'name' ] ?? $site;
            }
        }
        if ($siteID === '') {
            return '';
        }
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );
        if ( !isset( $JSONData[ 'data' ] ) || !is_array( $JSONData[ 'data' ] ) ) {
            return '';
        }
        $devices = $JSONData[ 'data' ];
        usort( $devices, function ( $a, $b ) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        // The device list has no uplink, only the details of each device do.
        $uplinks = [];
        foreach ( $devices as $device ) {
            $details = $this->getApiData( '/'.$siteID.'/devices/'.$device[ 'id' ] );
            if ( $details === [] || ( isset( $details[ 'statusCode' ] ) && $details[ 'statusCode' ] <> 404 ) ) {
                return '';
            }
            $uplinks[ $device[ 'id' ] ] = $details[ 'uplink' ][ 'deviceId' ] ?? self::HIERARCHY_ROOT_ID;
        }

        $nodes = [];
        $nodes[] = [ 'id' => self::HIERARCHY_ROOT_ID, 'parentId' => null, 'name' => $this->getHierarchyText( $siteName, 120, $site ), 'kind' => 'category' ];
        foreach ( $devices as $device ) {
            $value = array_filter( [ $device[ 'model' ] ?? '', $device[ 'ipAddress' ] ?? '', ( $device[ 'state' ] ?? 'ONLINE' ) === 'ONLINE' ? '' : $device[ 'state' ] ] );
            $nodes[] = [
                'id'           => $device[ 'id' ],
                'parentId'     => $this->getHierarchyParent( $device[ 'id' ], $uplinks ),
                'name'         => $this->getHierarchyText( $device[ 'name' ] ?? '', 120, $device[ 'id' ] ),
                'kind'         => 'instance',
                'displayValue' => $this->getHierarchyText( implode( ' | ', $value ), 100, '' )
            ];
        }
        if ( $this->ReadPropertyBoolean("hierarchyClients") ) {
            $clients = $this->getAllClients( $siteID );
            if ( $clients === null ) {
                return '';
            }
            usort( $clients, function ( $a, $b ) {
                return strcmp($a['name'] ?? '', $b['name'] ?? '');
            });
            foreach ( $clients as $client ) {
                $uplink = $client[ 'uplinkDeviceId' ] ?? '';
                $value = array_filter( [ $client[ 'type' ] ?? '', $client[ 'ipAddress' ] ?? '' ] );
                // The kind "variable" gives clients their own color in the tree.
                $nodes[] = [
                    'id'           => $client[ 'id' ],
                    'parentId'     => isset( $uplinks[ $uplink ] ) ? $uplink : self::HIERARCHY_ROOT_ID,
                    'name'         => $this->getHierarchyText( $client[ 'name' ] ?? '', 120, $client[ 'id' ] ),
                    'kind'         => 'variable',
                    'displayValue' => $this->getHierarchyText( implode( ' | ', $value ), 100, '' )
                ];
            }
        }
        $json = json_encode( [
            'format'        => 'de.burki24.symconecharts.tree',
            'formatVersion' => '1.0',
            'rootId'        => self::HIERARCHY_ROOT_ID,
            'nodes'         => $nodes
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return $json === false ? '' : $json;
    }

    // The API returns at most 200 clients per request, whatever limit is requested.
    private function getAllClients( string $siteID ): ?array
    {
        $clients = [];
        do {
            $JSONData = $this->getApiData( '/'.$siteID.'/clients?limit=200&offset='.count( $clients ) );
            if ( !isset( $JSONData[ 'data' ] ) || !is_array( $JSONData[ 'data' ] ) ) {
                return null;
            }
            $clients = array_merge( $clients, $JSONData[ 'data' ] );
        } while ( $JSONData[ 'data' ] !== [] && count( $clients ) < ( $JSONData[ 'totalCount' ] ?? 0 ) );
        return $clients;
    }

    // The format rejects unknown parents and cycles, such devices are attached to the root.
    private function getHierarchyParent( string $id, array $uplinks ): string
    {
        $parent = $uplinks[ $id ];
        if ( !isset( $uplinks[ $parent ] ) ) {
            return self::HIERARCHY_ROOT_ID;
        }
        $seen = [];
        for ( $current = $parent; isset( $uplinks[ $current ] ) && !isset( $seen[ $current ] ); $current = $uplinks[ $current ] ) {
            if ( $current === $id ) {
                return self::HIERARCHY_ROOT_ID;
            }
            $seen[ $current ] = true;
        }
        return $parent;
    }

    // The format limits the length and rejects control characters and blank names.
    private function getHierarchyText( string $text, int $maxLength, string $fallback ): string
    {
        $text = trim( (string) preg_replace( '/\p{Cc}/u', '', $text ) );
        return mb_substr( $text === '' ? $fallback : $text, 0, $maxLength );
    }

    public function ForwardData( string $JSONString ): string
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
                case "getConnectedClients":                    
                    $jsonString = $this->getConnectedClients(IPS_GetProperty( $data->InstanceID, 'ID' ));
                    return serialize($jsonString);
                    break;
                case "getDeviceName":
                    $this->SendDebug("UnifiGW", json_encode($data), 0);
                    if (isset($data->Param1)){
                        $jsonString = $this->getDeviceName($data->Param1);
                        return serialize($jsonString);
                    }
                    return '';
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
                case "getClientCountInSubnet":
                    return serialize( isset( $data->Param1 ) ? $this->getClientCountInSubnet( (string) $data->Param1 ) : -1 );
                    break;
                case "getSites":
					$array = $this->getSites();
return serialize($array);
					break;

			}

		}
        // Unknown API or missing parameter: the child checks for an empty result.
        return '';
    }

    public function GetConfigurationForm(): string {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $site = $this->ReadPropertyString( 'Site' );
        if ( !empty( $APIKey ) && $this->GetStatus() === 102 ) {
            $arrayOptions = $this->getSites();
        } else {
            $arrayOptions[] = array( 'caption' => 'default', 'value' => 'default' );
        }
        $arrayStatus = array();

        $arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instance is active' );
        $arrayStatus[] = array( 'code' => 201, 'icon' => 'error', 'caption' => 'Instance is faulty: Data request failed' );
        $arrayStatus[] = array( 'code' => 400, 'icon' => 'error', 'caption' => 'Instance is faulty: Bad Request' );
        $arrayStatus[] = array( 'code' => 401, 'icon' => 'error', 'caption' => 'Instance is faulty: Unauthorized' );
        $arrayStatus[] = array( 'code' => 403, 'icon' => 'error', 'caption' => 'Instance is faulty: Forbidden' );
        $arrayStatus[] = array( 'code' => 404, 'icon' => 'error', 'caption' => 'Instance is faulty: Not Found' );
        $arrayStatus[] = array( 'code' => 429, 'icon' => 'error', 'caption' => 'Instance is faulty: Rate Limit' );
        $arrayStatus[] = array( 'code' => 500, 'icon' => 'error', 'caption' => 'Instance is faulty: Server Error' );
        $arrayStatus[] = array( 'code' => 502, 'icon' => 'error', 'caption' => 'Instance is faulty: Bad Gateway' );

        $arraySort = array();
        #$arraySort = array( 'column' => 'DeviceName', 'direction' => 'ascending' );

        $arrayElements = array();
        $arrayElements[] = array( 'type' => 'Label','bold' => true, 'label' => $this->Translate('UniFi Device Gateway'));
        $arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Please create an API key under "UniFi Network > Settings > Control Plane > Integrations".'));
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'ServerAddress', 'caption' => 'Unifi Device IP', 'validate' => "^(([a-zA-Z0-9\\.\\-\\_]+(\\.[a-zA-Z]{2,3})+)|(\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b))$" );
        $arrayElements[] = array( 'type' => 'PasswordTextBox', 'name' => 'APIKey', 'caption' => 'API Key' );
        $arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'applicationVersion', 'caption' => $this->Translate('Show Application Version'), 'onChange' => 'IPS_RequestAction($id, "applicationVersionChanged", $applicationVersion);' );
        $arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'VersionTimer', 'caption' => $this->Translate('Version update interval (s) -> 0=Off'), 'minimum' => 0, 'enabled' => $this->ReadPropertyBoolean("applicationVersion") );
        $arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'networkHierarchy', 'caption' => $this->Translate('Create network hierarchy as JSON (for EChartsTree)'), 'onChange' => 'IPS_RequestAction($id, "networkHierarchyChanged", $networkHierarchy);' );
        $arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'HierarchyTimer', 'caption' => $this->Translate('Hierarchy update interval (s) -> 0=Off'), 'minimum' => 0, 'enabled' => $this->ReadPropertyBoolean("networkHierarchy") );
        $arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'hierarchyClients', 'caption' => $this->Translate('Include clients in the hierarchy'), 'enabled' => $this->ReadPropertyBoolean("networkHierarchy") );
        $arrayElements[] = array( 'type' => 'Select', 'name' => 'Site', 'caption' => 'Site', 'options' => $arrayOptions );
        $arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'TileTimer', 'caption' => $this->Translate('Tile update interval (s) -> 0=Off'), 'minimum' => 0 );

        if ( !empty( $APIKey ) && $this->GetStatus() === 102 ) {
            $version = $this->getNetworkVersion();
            if ($this->ReadPropertyBoolean("applicationVersion")) {
                 $this->storeApplicationVersion($version);
            }
            $arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Network Application Version:').' '.$version );
        } else {
            $arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('Network Application Version: not found') );
        }
        $arrayActions = array();
        $arrayOptions = array();
        $arrayOptions[] = array( 'type' => 'Button', 'width' => '220px', 'visible' => $this->ReadPropertyBoolean("applicationVersion"), 'label' => $this->Translate('Update Version'), 'onClick' => 'UNIFIGW_UpdateApplicationVersion($id);' );
        $arrayOptions[] = array( 'type' => 'Button', 'width' => '220px', 'visible' => $this->ReadPropertyBoolean("networkHierarchy"), 'label' => $this->Translate('Update Hierarchy'), 'onClick' => 'UNIFIGW_UpdateNetworkHierarchy($id);' );
        $arrayActions[] = array( 'type' => 'RowLayout', 'items' => $arrayOptions );

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
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT );
        curl_setopt( $ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT );
        $RawData = curl_exec( $ch );
        if ($RawData === false) {
            // Handle error
            $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
            curl_close( $ch );
            $this->SetStatus( 201 ); // Set status to error
            return [];
        }
        curl_close( $ch );
        $JSONData = json_decode( $RawData, true );
        if ( !is_array( $JSONData ) ) {
            $this->SendDebug("UnifiGW", "Invalid JSON response: " . substr( (string) $RawData, 0, 500 ), 0);
            $this->SetStatus( 201 ); // Set status to error
            return [];
        }
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
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT );
			curl_setopt( $ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT );
			$RawData = curl_exec( $ch );
			if ($RawData === false) {
				// Handle error
				$this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
				curl_close( $ch );
				$this->SetStatus( 201 ); // Set status to error
				return [];
			}
			curl_close( $ch );
			$JSONData = json_decode( $RawData, true );
			if ( is_array( $JSONData ) && isset( $JSONData[ 'statusCode' ] ) ) {
				if ($JSONData[ 'statusCode' ]<> 200) {
					// instance inactive
                    $this->SendDebug("UnifiGW", "Curl error: " . json_encode($JSONData), 0);
					return [];
				}        
			}
			$this->SendDebug("UnifiGW", "GetApiDataPost: " . $this->maskSecrets( (string) $RawData ), 0);
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
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT );
            curl_setopt( $ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT );
            $RawData = curl_exec( $ch );
            if ($RawData === false) {
                // Handle error
                $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
                curl_close( $ch );
                $this->SetStatus( 201 ); // Set status to error
                return [];
            }
            curl_close( $ch );
            $JSONData = json_decode( $RawData, true );
            if ( is_array( $JSONData ) && isset( $JSONData[ 'statusCode' ] ) ) {
                if ($JSONData[ 'statusCode' ]<> 200) {
                    // instance inactive
                    $this->SendDebug("UnifiGW", "Curl error: " . json_encode($JSONData), 0);
                    return [];
                }
            }
            $this->SendDebug("UnifiGW", "GetApiDataPut: " . $this->maskSecrets( (string) $RawData ), 0);
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
        $value = [];
        $this->SendDebug("UnifiGW", "getSiteWifis: " . $this->maskSecrets( (string) json_encode($JSONData) ) , 0);
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['error'] ) ) {
                if ($JSONData['error']['code']<> 200) {
                    // instance inactive
                    $this->SetStatus( $JSONData['error']['code'] );
                    $value[] = [
                        'caption'=>$this->Translate('Error loading data'),
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
        $value = [];
        $this->SendDebug("UnifiGW", "getNetworks: " .json_encode($JSONData) , 0);
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData['error'] ) ) {
                if ($JSONData['error']['code']<> 200) {
                    // instance inactive
                    $this->SetStatus( $JSONData['error']['code'] );
                    $value[] = [
                        'caption'=>$this->Translate('Error loading data'),
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
        $value = [];
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
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT );
        curl_setopt( $ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT );
        $RawData = curl_exec( $ch );
        if ($RawData === false) {
            // Handle error
            $this->SendDebug("UnifiGW", "Curl error: " . curl_error($ch), 0);
            curl_close( $ch );
            $this->SetStatus( 201 ); // Set status to error
            return '';
        }
        curl_close( $ch );
        $JSONData = json_decode( $RawData, true );
        if ( !is_array( $JSONData ) ) {
            $this->SendDebug("UnifiGW", "Invalid JSON response: " . substr( (string) $RawData, 0, 500 ), 0);
            $this->SetStatus( 201 ); // Set status to error
            return '';
        }
        $this->SendDebug("UnifiGW", json_encode($JSONData), 0);
        if ( isset( $JSONData[ 'statusCode' ] ) ) {
            if ($JSONData[ 'statusCode' ]<> 200) {
                // instance inactive
			    $this->SetStatus( $JSONData[ 'statusCode' ] );
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
        if (version_compare($version, '10.0', '<')) {
            $this->SendDebug('UnifiGW', 'Network version ' . $version . ' is below 10.0. getWifiDetails requires >= 10.0.', 0);
            return false;
        }
        return true;
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
        $value = [];
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
        }
        return $value;
    }

    public function updateNetworkSettings(string $networkID, string $jsonPayload):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $this->SendDebug("UnifiGW", "updateNetworkSettings: " . $jsonPayload, 0);
        $this->SendDebug("UnifiGW", "updateNetworkSettings: " . '/'.$siteID.'/networks/'.$networkID.'/', 0);
        $JSONData=$this->getApiDataPut( '/'.$siteID.'/networks/'.$networkID, $jsonPayload );
        return json_encode($JSONData);
    }

    public function updateWifiSettings(string $wifiID, string $jsonPayload):string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $this->SendDebug("UnifiGW", "updateWifiSettings: " . $this->maskSecrets( $jsonPayload ), 0);
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

     public function getClientData(string $clientID): string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients/'.$clientID );
        return json_encode($JSONData);
    }

	public function getDevices(): array {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );
        $value = [];

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
        }
        return $value;
    }

    public function getDeviceStats(string $deviceID): string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID.'/statistics/latest' );
        return json_encode($JSONData);        
    }

    public function getConnectedClients(string $deviceID): string {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients?limit=500' );
        $count = 0;
        $data=$JSONData['data'] ?? [];
        if (is_array($data)) {
            foreach ($data as $item) {            
                if (isset($item['uplinkDeviceId']) && $item['uplinkDeviceId'] === $deviceID) {
                    $count++;
               }
            }
        }
        return json_encode(['connectedClients' => $count]);
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

    private function getInstanceIDForGuid( string $id, string $guid ): int
        {
            $instanceIDs = IPS_GetInstanceListByModuleID( $guid );
            foreach ( $instanceIDs as $instanceID ) {
                if ( IPS_GetProperty( $instanceID, 'ID' ) == $id ) {
                    $instance=IPS_GetInstance($instanceID);
                    if ($instance['ConnectionID']==$this->InstanceID) {
                        return $instanceID;
                    }
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
        $value = [];

        if ( isset( $JSONData[ 'data' ] ) && is_array( $JSONData[ 'data' ] ) )
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
        if ( isset( $JSONData[ 'data' ] ) && is_array( $JSONData[ 'data' ] ) ) {
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
            if ( isset( $JSONData[ 'data' ] ) && is_array( $JSONData[ 'data' ] ) ) {
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
            if ( isset( $JSONData[ 'data' ] ) && is_array( $JSONData[ 'data' ] ) ) {
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