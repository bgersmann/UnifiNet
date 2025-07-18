<?php

declare(strict_types=1);

class UnifiClient extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->ConnectParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
        $this->RegisterPropertyString('ID', '');
        $this->RegisterPropertyInteger('Timer', 0);
		$this->RegisterPropertyBoolean("MACAnzeigen", 0);
		$this->RegisterPropertyBoolean("IDAnzeigen", 0);
        $this->RegisterTimer('Collect Data', 0, "UNIFICL_Send(\$_IPS['TARGET'],'getClientData','');");
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
			$this->MaintainVariable( 'ID', $this->Translate( 'Client ID' ), 3, '', $vpos++, $this->ReadPropertyBoolean("IDAnzeigen") );
			$this->MaintainVariable( 'ClientType', $this->Translate( 'Client Type' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'ClientIP', $this->Translate( 'Client IP' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'ConnectedAt', $this->Translate( 'Connected At' ), 1, [ 'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME ], $vpos++, 1 );
			$this->MaintainVariable( 'Online', $this->Translate( 'Online' ), 0, [ 'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON'=> 'network-wired','OPTIONS'=>'[{"ColorDisplay":16077123,"Value":false,"Caption":"Offline","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":16077123,"Color":-1},{"ColorDisplay":1692672,"Value":true,"Caption":"Online","IconValue":"","IconActive":false,"ColorActive":true,"ColorValue":1692672,"Color":-1}]'], $vpos++, 1 );
			$this->MaintainVariable( 'UplinkDevice', $this->Translate( 'Uplink Device' ), 3, '', $vpos++, 1 );
			$this->MaintainVariable( 'MAC', $this->Translate( 'Client MAC' ), 3, '', $vpos++, $this->ReadPropertyBoolean("MACAnzeigen") );
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

		public function Send(string $api, string $param1)
		{	
			if ($this->HasActiveParent()) {
				$this->SendDataToParent(json_encode(['DataID' => '{4A5538F1-1C38-198A-3144-D806E0DADF87}',
					'Api' => $api,
					'InstanceID' => $this->InstanceID,
					'Param1' => $param1
					]));
			}			
		}		


		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString,true);
			If ($data['id']== $this->InstanceID) {
				//IPS_LogMessage('UNIFICL-'.$this->InstanceID,utf8_decode($data['data']));
				switch($data['Api']) {
					case "getClients":
						$this->UpdateFormField("ID", "options", $data['data']);
						$this->SetBuffer("clients", $data['data']);
						break;
					case "getClientData":
						$array = json_decode($data['data'],true);
						if ( is_array( $array ) && isset( $array ) ) {
							if ( isset( $array[ 'statusCode' ] ) ) {
								$this->SetValue( 'Online', false );
							} else {
								$this->SetValue( 'ClientName', $array[ 'name' ] );
								if ($this->ReadPropertyBoolean("IDAnzeigen")) {
									$this->SetValue( 'ID', $array[ 'id' ] );
								}								
								$this->SetValue( 'ClientType', $array[ 'type' ] );
								if ( isset( $array[ 'ipAddress' ] ) ) {
									$this->SetValue( 'ClientIP', $array[ 'ipAddress' ] );
								}
								$this->SetValue( 'ConnectedAt', strtotime( $array[ 'connectedAt' ] ) );
								$this->SetValue( 'Online', true );
								if ( isset( $array['uplinkDeviceId'] ) ) {
									$this->Send('getDeviceName',$array['uplinkDeviceId']);									
								}
								if ($this->ReadPropertyBoolean("MACAnzeigen")) {
									$this->SetValue( 'MAC', $array[ 'macAddress' ] );
								}
							}
						}
						break;
					case "getDeviceName":
						$this->SendDebug("UnifiCL", $data['data'], 0);
						$this->SetValue( 'UplinkDevice', $data['data']);
						break;
				}
			}			
		}

		public function GetConfigurationForm(){
			if ($this->HasActiveParent()) {
				$this->Send("getClients",'');
			}			
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );

			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'label' => $this->Translate('UniFi Client') );
			$arrayElements[] = array( 'type' => 'NumberSpinner', 'name' => 'Timer', 'caption' => 'Timer (s) -> 0=Off' );
			$Bufferdata = $this->GetBuffer("clients");
			if ($Bufferdata=="") {
				$arrayOptions[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayOptions=json_decode($Bufferdata);
			}		
			$arrayElements[] = array( 'type' => 'Select', 'name' => 'ID', 'caption' => 'Client ID', 'options' => $arrayOptions );
			$arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'MACAnzeigen', 'caption' => $this->Translate('Show MAC') );
			$arrayElements[] = array( 'type' => 'CheckBox', 'name' => 'IDAnzeigen', 'caption' => $this->Translate('Show ID') );
			

			$arrayActions = array();
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Clients auslesen', 'onClick' => 'UNIFICL_Send($id,"getClients","");' );
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Daten auslesen', 'onClick' => 'UNIFICL_Send($id,"getClientData","");' );
		
			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );
			
    	}
	}