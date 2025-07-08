<?php

declare( strict_types = 1 );

require_once __DIR__.'/../libs/globalFun.php';
// globale Funktionen

class UnifiDevice extends IPSModule
{
    use myFunctions;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString( 'ServerAddress', '192.168.178.1' );
        $this->RegisterPropertyString( 'APIKey', '' );
        $this->RegisterPropertyString( 'Site', 'default' );
        $this->RegisterPropertyString( 'ID', '' );
        $this->RegisterPropertyInteger( 'Timer', '0' );

        $this->RegisterTimer( 'Collect Data', 0, "UNIFINT_getDataDevice(\$_IPS['TARGET']);" );
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
        $this->MaintainVariable( 'FirmwareUpdate', $this->Translate( 'FirmwareUpdate' ), 0, '', $vpos++, 1 );
        $this->MaintainVariable( 'UptimeSec', $this->Translate( 'UptimeSec' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_DURATION, 'FORMAT'=> 2 ], $vpos++, 1 );
        $this->MaintainVariable( 'UplinkTX', $this->Translate( 'UplinkTX' ), 2, '', $vpos++, 1 );
        $this->MaintainVariable( 'UplinkRX', $this->Translate( 'UplinkRX' ), 2, '', $vpos++, 1 );
        $this->MaintainVariable( 'Online', $this->Translate( 'Online' ), 0, '', $vpos++, 1 );

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

        $arrayStatus = array();

        $arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );

        $arrayElements = array();

        $arrayElements[] = array( 'type' => 'Label', 'label' => 'UniFi Device' );

        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'ServerAddress', 'caption' => 'Unifi Device IP', 'validate' => "^(([a-zA-Z0-9\\.\\-\\_]+(\\.[a-zA-Z]{2,3})+)|(\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b))$" );
        $arrayElements[] = array( 'type' => 'ValidationTextBox', 'name' => 'APIKey', 'caption' => 'APIKey' );

        #$arrayOptions[] = array( 'caption' => 'Test22', 'value' => 'Test' );
        $arrayElements[] = array( 'type' => 'Select', 'name' => 'Site', 'caption' => 'Site', 'options' => $arrayOptions );

        $arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );

        unset( $arrayOptions );
        #$arrayOptions[] = array( 'caption' => 'Test', 'value' => 'Test' );

        if ( !empty( $site ) and !empty( $APIKey ) ) {
            $siteID = $this->getSiteID( $site );
            $arrayOptions = $this->getSites();
            $arrayOptions = $this->getDevices();
        } else {
            $arrayOptions[] = array( 'caption' => '', 'value' => '' );
        }

        $arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Device ID', 'options' => $arrayOptions );

        $arrayActions = array();

        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Daten Holen', 'onClick' => 'UNIFINT_getDataDevice($id);' );
        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Devices Holen', 'onClick' => 'UNIFINT_getDevices($id);' );
        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Sites Holen', 'onClick' => 'UNIFINT_getSites($id);' );
        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Stats Holen', 'onClick' => 'UNIFINT_getStatsDevice($id);' );

        return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );

    }

}