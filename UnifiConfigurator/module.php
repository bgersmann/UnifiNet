<?php

declare(strict_types=1);
	class UnifiConfigurator extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			$this->RequireParent('{A792D3EC-FEC5-A8E6-F792-E141097C6AB0}');
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
				//IPS_LogMessage('UNIFICL-'.$this->InstanceID,utf8_decode($data['data']));
				switch($data['Api']) {
					case "getDevicesConfig":
						$this->UpdateFormField("UnifiDevices", "values", $data['data']);
						$this->SetBuffer("configurator", $data['data']);					
						break;
				}
			}			
		}


		 public function GetConfigurationForm()
		{
			if ($this->HasActiveParent()) {
				$this->Send("getDevicesConfig");
			}
			$arrayOptions[] = array( 'caption' => 'default', 'value' => 'default' );
			
			$arrayStatus = array();
			$arrayStatus[] = array( 'code' => 102, 'icon' => 'active', 'caption' => 'Instanz ist aktiv' );

			$arraySort = array();
			#$arraySort = array( 'column' => 'Name', 'direction' => 'ascending' );

			$arrayColumns = array();
			$arrayColumns[] = array( 'caption' => 'Name', 'name' => 'Name', 'width' => 'auto', 'add' => '' );
			$arrayColumns[] = array( 'caption' => 'Typ', 'name' => 'Type', 'width' => '200px', 'add' => '' );
			$arrayColumns[] = array( 'caption' => 'IP', 'name' => 'IP', 'width' => '200px', 'add' => '' );
			$arrayColumns[] = array( 'caption' => 'ID', 'name' => 'ID', 'width' => '300px', 'add' => '' );
			$arrayValues = array();

			$Bufferdata = $this->GetBuffer("configurator");
			if ($Bufferdata=="") {
				$arrayValues[] = array( 'caption' => 'Test', 'value' => '' );
			} else {
				$arrayValues=json_decode($Bufferdata);
			}
			$arrayElements = array();
			$arrayElements[] = array( 'type' => 'Label', 'label' => 'UniFi Device Configurator' );
			$arrayElements[] = array( 'type' => 'Configurator', 'name' => 'UnifiDevices', 'caption' => 'Unifi Devices', 'rowCount' => 10, 'delete' => false, 'sort' => $arraySort, 'columns' => $arrayColumns, 'values' => $arrayValues );

			$arrayActions = array();
			$arrayActions[] = array( 'type' => 'Button', 'label' => 'Geräte auslesen', 'onClick' => 'UNIFICG_Send($id,"getDevicesConfig");');

			return JSON_encode( array( 'status' => $arrayStatus, 'elements' => $arrayElements, 'actions' => $arrayActions ) );

		}
	}