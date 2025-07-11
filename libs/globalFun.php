<?php

declare( strict_types = 1 );

trait myFunctions
 {
    public function getApiData( $endpoint = '' ) {
        $ServerAddress = $this->ReadPropertyString( 'ServerAddress' );

        $APIKey = $this->ReadPropertyString( 'APIKey' );

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
        $JSONData = json_decode( $RawData, true );
        return $JSONData;
    }

    public function getSiteID( $site = 'default' ) {
        $JSONData = $this->getApiData();
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            foreach ( $JSONData[ 'data' ] as $item ) {
                if ( $item[ 'internalReference' ] == $site ) {
                    return $item[ 'id' ];
                }
            }
        }
        return '';
    }

    public function getDevices() {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices?limit=200' );

        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            $devices = $JSONData[ 'data' ];
            usort( $devices, function ( $a, $b ) {
        return $a[ 'name' ]>$b[ 'name' ];
        });

            foreach ( $devices as $device ) {
                $value[] = [
                    'caption'=>$device[ 'name' ],
                    'value'=> $device[ 'id' ]
                ];
            }

            return $value;
        }
    }

    public function getClients() {
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients?limit=200' );
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            $clients = $JSONData[ 'data' ];
            usort( $clients, function ( $a, $b ) {
            return $a[ 'name' ]>$b[ 'name' ];});

            foreach ( $clients as $client ) {
                $value[] = [
                    'caption'=>$client[ 'name' ],
                    'value'=> $client[ 'id' ]
                ];
            }
            return $value;
        }
    }

    public function getDataDevice() {
        $deviceID = $this->ReadPropertyString( 'ID' );
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID );
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
                            $this->MaintainVariable( 'Port_'.$port[ 'idx' ], $this->Translate( 'Port '.$port[ 'idx' ] ), 3, '', $vpos++, 1 );
                            $PortText='';
                            if ( isset( $port[ 'poe' ] ) ) {
                                $POE=' POE-'.$port[ 'poe' ]['state'].' - '.$port[ 'poe' ]['enabled'].' - '.$port[ 'poe' ]['standard'];
                            } else 
                            {
                                $POE='';
                            }
                            if ($port[ 'state' ]== 'UP') {
                                $PortText=$port[ 'connector' ].'-'.$port[ 'idx' ].' - '.$port[ 'state' ].' - '.(( isset($port[ 'speedMbps' ])==true) ? $port[ 'speedMbps' ] : '0') .'Mbps'.$POE ;
                            } else {
                                $PortText=$port[ 'connector' ]. '-'.$port[ 'idx' ].' - '.$port[ 'state' ];
                            }
                            
                            $this->SetValue( 'Port_'.$port[ 'idx' ], $PortText);
                        }
                    }
                    if ( isset( $JSONData[ 'interfaces' ][ 'radios' ] ) ) {
                        $radios = $JSONData[ 'interfaces' ][ 'radios' ];
                        $vpos = 2000;
                        foreach ( $radios as $radio ) {
                            $vpos++;
                            $this->MaintainVariable( 'Port_'.$vpos, $this->Translate( 'WLAN '.$radio[ 'frequencyGHz' ] ), 3, '', $vpos, 1 );
                            $this->SetValue( 'Port_'.$vpos, $radio[ 'frequencyGHz' ].'GHz - CH'.$radio[ 'channel'].' - '.$radio[ 'wlanStandard'].' - '.$radio[ 'channelWidthMHz' ].'MHz' );
                        }
                    }
                }
            }
            $this->getStatsDevice();
        }
    }

    public function getDataClient() {
        $clientID = $this->ReadPropertyString( 'ID' );

        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/clients/'.$clientID );
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            if ( isset( $JSONData[ 'statusCode' ] ) ) {
                $this->SetValue( 'Online', false );
            } else {
                $this->SetValue( 'ClientName', $JSONData[ 'name' ] );
                $this->SetValue( 'ID', $JSONData[ 'id' ] );
                $this->SetValue( 'ClientType', $JSONData[ 'type' ] );
                if ( isset( $JSONData[ 'ipAddress' ] ) ) {
                    $this->SetValue( 'ClientIP', $JSONData[ 'ipAddress' ] );
                }
                $this->SetValue( 'ConnectedAt', strtotime( $JSONData[ 'connectedAt' ] ) );
                $this->SetValue( 'Online', true );
            }
        }
    }

    public function getStatsDevice() {
        $deviceID = $this->ReadPropertyString( 'ID' );
        $site = $this->ReadPropertyString( 'Site' );
        $siteID = $this->getSiteID( $site );
        $JSONData = $this->getApiData( '/'.$siteID.'/devices/'.$deviceID.'/statistics/latest' );
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            #var_dump( $JSONData );
            $this->SetValue( 'UptimeSec', $JSONData[ 'uptimeSec' ] );
            $this->SetValue( 'UplinkTX', round( $JSONData[ 'uplink' ][ 'txRateBps' ]/1000/1000, 4 ) );
            $this->SetValue( 'UplinkRX', round( $JSONData[ 'uplink' ][ 'rxRateBps' ]/1000/1000, 4 ) );
        }
    }

    public function getSites() {
        $JSONData = $this->getApiData();
        if ( is_array( $JSONData ) && isset( $JSONData ) ) {
            $sites = $JSONData[ 'data' ];
            foreach ( $sites as $site ) {
                $value[] = [
                    'caption'=>$site[ 'internalReference' ],
                    'value'=> $site[ 'internalReference' ]
                ];
            }
            return $value;
        }
    }

}
