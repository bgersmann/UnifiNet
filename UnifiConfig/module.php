<?php

declare( strict_types = 1 );

require_once __DIR__.'/../libs/globalFun.php';
// globale Funktionen

class UnifiConfigurator extends IPSModule
{
    use myFunctions;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString( 'ServerAddress', '192.168.178.1' );
        $this->RegisterPropertyString( 'APIKey', '' );
        $this->RegisterPropertyString( 'Site', 'default' );
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
    }

    public function GetConfigurationForm()
    {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $site = $this->ReadPropertyString( 'Site' );
        if ( !empty( $APIKey ) ) {
            $arrayOptions = $this->getSites();
        } else {
            $arrayOptions[] = array( 'caption' => 'default', 'value' => 'default' );
        }
        if ( !empty( $site ) and !empty( $APIKey ) ) {
            $siteID = $this->getSiteID( $site );
        }
        $arrayStatus = array();

        $arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );

        $arraySort = array();
        #$arraySort = array( 'column' => 'DeviceName', 'direction' => 'ascending' );

        $arrayColumns = array();
        $arrayColumns[] = array( 'caption' => 'Name', 'name' => 'Name', 'width' => '200px', 'add' => '' );
        $arrayColumns[] = array( 'caption' => 'Typ', 'name' => 'Type', 'width' => '200px', 'add' => '' );
        $arrayColumns[] = array( 'caption' => 'IP', 'name' => 'IP', 'width' => '200px', 'add' => '' );
        $arrayColumns[] = array( 'caption' => 'ID', 'name' => 'ID', 'width' => '50px', 'add' => '' );
        $arrayValues = array();

        if ( !empty( $siteID ) and !empty( $APIKey ) ) {
            $arrayValues = $this->getDevicesConfig();
        } else {

        }

        $arrayElements = array();
        $arrayElements[] = array( 'type' => 'Label', 'label' => 'UniFi Device Configurator' );
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'ServerAddress', 'caption' => 'Unifi Device IP', 'validate' => "^(([a-zA-Z0-9\\.\\-\\_]+(\\.[a-zA-Z]{2,3})+)|(\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b))$" );
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'APIKey', 'caption' => 'APIKey' );
        $arrayElements[] = array( 'type' => 'Select', 'name' => 'Site', 'caption' => 'Site', 'options' => $arrayOptions );
        $arrayElements[] = array( 'type' => 'Configurator', 'name' => 'UnifiDevices', 'caption' => 'Unifi Devices', 'rowCount' => 10, 'delete' => false, 'sort' => $arraySort, 'columns' => $arrayColumns, 'values' => $arrayValues );

        $arrayActions = array();

        return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );

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

    public function getDevicesConfig() {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );
        $APIKey = $this->ReadPropertyString( 'APIKey' );
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );
        if ( is_array( $JSONData ) && isset( $JSONData ) )
        {
            $devices = $JSONData[ 'data' ];
            usort( $devices, function ( $a, $b ) {
                return $a[ 'name' ]>$b[ 'name' ];
                });
            foreach ( $devices as $device ) {
                $value[] = array(
                    'Name'	=>$device[ 'name' ],
                    'Type'	=>$device[ 'model' ],
                    'ID'		=>$device[ 'id' ],
                    'IP'		=>$device[ 'ipAddress' ],
                    'instanceID'	=>$this->getInstanceIDForGuid( $device[ 'id' ], '{0895574E-CB42-0139-1538-C1E38BABC42B}' ),
                    'create' 		=>[
                        'moduleID'      => '{0895574E-CB42-0139-1538-C1E38BABC42B}',
                        'configuration' => [
                            'ServerAddress'	=> $ServerAddress,
                            'APIKey' 	=> $APIKey,
                            'Site'		=> $site,
                            'ID'	=> $device[ 'id' ]
                        ],
                        'name' => $device[ 'name' ]
                    ] );

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
                    $value[] = array(
                        'Name'	=>$client[ 'name' ],
                        'Type'	=>'Client',
                        'ID'		=>$client[ 'id' ],
                        'IP'		=>isset( $client[ 'ipAddress' ] ) ? $client[ 'ipAddress' ] : '',
                        'instanceID'	=>$this->getInstanceIDForGuid( $client[ 'id' ], '{E74A3667-F11A-55BF-FDAD-749027B9047F}' ),
                        'create' 		=>[
                            'moduleID'      => '{E74A3667-F11A-55BF-FDAD-749027B9047F}',
                            'configuration' => [
                                'ServerAddress'	=> $ServerAddress,
                                'APIKey' 	=> $APIKey,
                                'Site'		=> $site,
                                'ID'	=> $client[ 'id' ]
                            ],
                            'name' => $client[ 'name' ]
                        ] );
                    }
                }
                return $value;
            }
        }