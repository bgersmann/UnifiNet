<?php

declare( strict_types = 1 );
require_once __DIR__.'/../libs/globalFun.php';
// globale Funktionen

class UnifiClient extends IPSModule
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

        $this->RegisterTimer( 'Collect Data', 0, "UNIFICL_getDataClient(\$_IPS['TARGET']);" );
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
        $this->MaintainVariable( 'ClientName', $this->Translate( 'Client Name' ), 3, '', $vpos++, 1 );
        $this->MaintainVariable( 'ID', $this->Translate( 'Client ID' ), 3, '', $vpos++, 1 );
        $this->MaintainVariable( 'ClientType', $this->Translate( 'Client Type' ), 3, '', $vpos++, 1 );
        $this->MaintainVariable( 'ClientIP', $this->Translate( 'Client IP' ), 3, '', $vpos++, 1 );
        $this->MaintainVariable( 'ConnectedAt', $this->Translate( 'Verbunden Seit' ), 3, '', $vpos++, 1 );
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

        $arrayElements[] = array( 'type' => 'Label', 'label' => 'UniFi Client Device' );

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
            $arrayOptions = $this->getClients();
        } else {
            $arrayOptions[] = array( 'caption' => '', 'value' => '' );
        }

        $arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Deivce ID', 'options' => $arrayOptions );

        $arrayActions = array();

        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Daten Holen', 'onClick' => 'UNIFICL_getDataClient($id);' );
        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Clients Holen', 'onClick' => 'UNIFICL_getClients($id);' );
        $arrayActions[] = array( 'type' => 'Button', 'label' => 'Sites Holen', 'onClick' => 'UNIFICL_getSites($id);' );
        //$arrayActions[] = array( 'type' => 'Button', 'label' => 'Stats Holen', 'onClick' => 'UNIFICL_getStatsClient($id);' );

        return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );

    }

}