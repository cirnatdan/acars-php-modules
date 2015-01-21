<?php
/**
 * phpVMS - Virtual Airline Administration Software
 * Copyright (c) 2008 Nabeel Shahzad
 * For more information, visit www.phpvms.net
 *	Forums: http://www.phpvms.net/forum
 *	Documentation: http://www.phpvms.net/docs
 *
 * phpVMS is licenced under the following license:
 *   Creative Commons Attribution Non-commercial Share Alike (by-nc-sa)
 *   View license.txt in the root, or visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 *
 * @author Jeffrey Kobus
 * @copyright Copyright (c) 2010, Jeffrey Kobus
 * @link http://www.fs-products.net
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 * 
 *
 *
 * CAVacars_free is based on kACARS_free modul nade by Jeffrey Kobus
 * Modified by Bruno Sostaric
 * Version 1.0.7
 */



class CAVacars_free extends CodonModule
{	
	
	public function index()
	{
		if($_SERVER['REQUEST_METHOD'] === 'POST')
		{ 
			// Site Settings
			$showLights = 1;     		// Set to 1 if want to log landing lights on/off
			$charter	= 1;			// Allow Charter flights to be flown (Includes abilty to change aircraft) 0=no 1=yes
			$logPause	= 1;			// 0=Log Pauses or 1=Do NOT Log Pauses
			$logEngines = 1;   			// Log engines ON/OFF
			$selectAircraft=0;          // Allow selecting of aircraft for scheduled flight 1-yes, 0-no

			
			$postText = file_get_contents('php://input');			
			$encoding = mb_detect_encoding($postText);
			$rec_xml = trim(iconv($encoding, "UTF-8", $postText));			
			$xml = simplexml_load_string($rec_xml);		
			
			if(!$xml)
			{
				echo "not xml";
				return;	
			}
			
			$case = strtolower($xml->switch->data);
			switch($case)
			{				
				case 'verify':		
					$results = Auth::ProcessLogin($xml->verify->pilotID, $xml->verify->password);		
					if ($results)
					{						
						$params = array('loginStatus' => '1');
					}
					else
					{
						$params = array('loginStatus' => '0');
					}
					
					// Send Site Settings
					$params['showLights']      	= $showLights;
					$params['charter']			= $charter;
					$params['logPause'] 		= $logPause;
					$params['logEngines'] 		= $logEngines;
					$params['selectAircraft'] 	= $selectAircraft;
					
					$send = self::sendXML($params);
					
				break;
				
				case 'getbid':							
					
					$pilotid = PilotData::parsePilotID($xml->verify->pilotID);
					$pilotinfo = PilotData::getPilotData($pilotid);
					$biddata = SchedulesData::getLatestBid($pilotid);
					$aircraftinfo = OperationsData::getAircraftByReg($biddata->registration);
					
					if(count($biddata) == 1)
					{		
						if($aircraftinfo->enabled == 1)
						{
							$params = array(
								'flightStatus' 	   => '1',
								'flightNumber'     => $biddata->code.$biddata->flightnum,
								'aircraftReg'      => $biddata->registration,
								'aircraftICAO'     => $aircraftinfo->icao,
								'aircraftFullName' => $aircraftinfo->fullname,
								'flightLevel'      => $biddata->flightlevel,
								'aircraftMaxPax'   => $aircraftinfo->maxpax,
								'aircraftCargo'    => $aircraftinfo->maxcargo,
								'depICAO'          => $biddata->depicao,
								'arrICAO'          => $biddata->arricao,
								'route'            => $biddata->route,
								'depTime'          => $biddata->deptime,
								'arrTime'          => $biddata->arrtime,
								'aircraftName'     => $aircraftinfo->name,
								'flightType'       => $biddata->flighttype,
								'flDistance'  	   => $biddata->distance
							);					
						}
					else
						{	
							$params = array(
								'flightStatus' 	   => '3');		// Aircraft Out of Service.							
						}			
					}		
					else		
					{
						$params = array('flightStatus' => '2');	// You have no bids!								
					}
					$send = $this->sendXML($params);
					
				break;
				
				case 'getflight':
					
					$flightinfo = SchedulesData::getProperFlightNum($xml->pirep->flightNumber);
					
					$params = array(
						's.code' => $flightinfo['code'],
						's.flightnum' => $flightinfo['flightnum'],
						's.enabled' => 1,
					);
					
					$biddata = SchedulesData::findSchedules($params, 1);
					$aircraftinfo = OperationsData::getAircraftByReg($biddata[0]->registration);
					
					if(count($biddata) == 1)
					{		
						$params = array(
							'flightStatus' 	   => '1',
							'flightNumber'     => $biddata[0]->code.$biddata[0]->flightnum,
							'aircraftReg'      => $biddata[0]->registration,
							'aircraftICAO'     => $aircraftinfo->icao,
							'aircraftFullName' => $aircraftinfo->fullname,
							'flightLevel'      => $biddata[0]->flightlevel,
							'aircraftMaxPax'   => $aircraftinfo->maxpax,
							'aircraftCargo'    => $aircraftinfo->maxcargo,
							'depICAO'          => $biddata[0]->depicao,
							'arrICAO'          => $biddata[0]->arricao,
							'route'            => $biddata[0]->route,
							'depTime'          => $biddata[0]->deptime,
							'arrTime'          => $biddata[0]->arrtime,
							'flightTime'       => $biddata[0]->flighttime,
							'flightType'       => $biddata[0]->flighttype,
							'aircraftName'     => $aircraftinfo->name,
							'aircraftRange'    => $aircraftinfo->range,
							'aircraftWeight'   => $aircraftinfo->weight,
							'aircraftCruise'   => $aircraftinfo->cruise
							);
					}			
					else		
					{	
						$params = array('flightStatus' 	   => '2');								
					}
					
					$send = $this->sendXML($params);
				break;			
				
				case 'liveupdate':	
					
					$pilotid = PilotData::parsePilotID($xml->verify->pilotID);
					$lat = str_replace(",", ".", $xml->liveupdate->latitude);
					$lon = str_replace(",", ".", $xml->liveupdate->longitude);
					
					# Get the distance remaining
					$depapt = OperationsData::GetAirportInfo($xml->liveupdate->depICAO);
					$arrapt = OperationsData::GetAirportInfo($xml->liveupdate->arrICAO);
					$dist_remain = round(SchedulesData::distanceBetweenPoints(
						$lat, $lon,	$arrapt->lat, $arrapt->lng));
					
					# Estimate the time remaining
					if($xml->liveupdate->groundSpeed > 0)
					{
						$Minutes = round($dist_remain / $xml->liveupdate->groundSpeed * 60);
						$time_remain = self::ConvertMinutes2Hours($Minutes);
					}
					else
					{
						$time_remain = '00:00';
					}					
					
					$fields = array(
						'pilotid'        =>$pilotid,
						'flightnum'      =>$xml->liveupdate->flightNumber,
						'pilotname'      =>'',
						'aircraft'       =>$xml->liveupdate->registration,
						'lat'            =>$lat,
						'lng'            =>$lon,
						'heading'        =>$xml->liveupdate->heading,
						'alt'            =>$xml->liveupdate->altitude,
						'gs'             =>$xml->liveupdate->groundSpeed,
						'depicao'        =>$xml->liveupdate->depICAO,
						'arricao'        =>$xml->liveupdate->arrICAO,
						'deptime'        =>$xml->liveupdate->depTime,
						'arrtime'        =>'',
						'route'          =>$xml->liveupdate->route,
						'distremain'     =>$dist_remain,
						'timeremaining'  =>$time_remain,
						'phasedetail'    =>$xml->liveupdate->status,
						'online'         =>'',
						'client'         =>'CAVacars_free',
						);
					
					ACARSData::UpdateFlightData($pilotid, $fields);	

					$totaldistance = round(SchedulesData::distanceBetweenPoints($depapt->lat, $depapt->lng, $arrapt->lat, $arrapt->lng));
					if ($totaldistance == 0)
						{
							$params = array('progress' => '0');    
						}
						else
						{
							$percomplete = ABS(number_format(((($totaldistance - $dist_remain) / $totaldistance) * 100), 2));
							$params = array('progress' => $percomplete);
						}
					$send = $this->sendXML($params);
                              
                break; 
				
				case 'getdistance':	
					
					# Get the distance for charter
					$depapt = OperationsData::GetAirportInfo($xml->liveupdate->depICAO);
					$arrapt = OperationsData::GetAirportInfo($xml->liveupdate->arrICAO);
					
					$fields = array(
						'depicao'        =>$xml->liveupdate->depICAO,
						'arricao'        =>$xml->liveupdate->arrICAO,
						);

					$totaldistance = round(SchedulesData::distanceBetweenPoints($depapt->lat, $depapt->lng, $arrapt->lat, $arrapt->lng));
					$params = array('distance' => $totaldistance);    
					$send = $this->sendXML($params);
                              
                break; 
				
				case 'pirep':						
					
					$flightinfo = SchedulesData::getProperFlightNum($xml->pirep->flightNumber);
					$code = $flightinfo['code'];
					$flightnum = $flightinfo['flightnum'];
					
					$pilotid = PilotData::parsePilotID($xml->verify->pilotID);
					
					# Make sure airports exist:
					#  If not, add them.
					
					if(!OperationsData::GetAirportInfo($xml->pirep->depICAO))
					{
						OperationsData::RetrieveAirportInfo($xml->pirep->depICAO);
					}
					
					if(!OperationsData::GetAirportInfo($xml->pirep->arrICAO))
					{
						OperationsData::RetrieveAirportInfo($xml->pirep->arrICAO);
					}
					
					# Get aircraft information
					$reg = trim($xml->pirep->registration);
					$ac = OperationsData::GetAircraftByReg($reg);
					
					# Load info
					/* If no passengers set, then set it to the cargo */
					$load = $xml->pirep->pax;
					if(empty($load))
						$load = $xml->pirep->cargo;						
					
					/* Fuel conversion - CAVacars reports in kg */
					$fuelused = $xml->pirep->fuelUsed;
					if(Config::Get('LiquidUnit') == '0')
					{
						# Divide by density since d = mass * volume
						$fuelused = $fuelused / .8075;
					}
					# Convert lbs to gallons
					elseif(Config::Get('LiquidUnit') == '1')
					{
						$fuelused = ($fuelused * 2.20462) / 6.84;
					}
					# Convert kg to lbs
					elseif(Config::Get('LiquidUnit') == '3')
					{
						$fuelused = $fuelused * 2.20462;
					}					
					
					$data = array(
						'pilotid'			=>$pilotid,
						'code'				=>$code,
						'flightnum'			=>$flightnum,
						'depicao'			=>$xml->pirep->depICAO,
						'arricao'			=>$xml->pirep->arrICAO,
						'aircraft'			=>$ac->id,
						'flighttime'		=>$xml->pirep->flightTime,
						'flighttype'		=>$xml->pirep->flightType,
						'submitdate'		=>'UTC_TIMESTAMP()',
						'comment'			=>$xml->pirep->comments,
						'fuelused'			=>$fuelused,
						'route'          	=>$xml->pirep->route,
						'source'			=>'CAVacars_free',
						'load'				=>$load,
						'landingrate'		=>$xml->pirep->landing,
						'log'				=>$xml->pirep->log
					);
					
					$ret = ACARSData::FilePIREP($pilotid, $data);		
					
					if ($ret)
					{
						$params = array(
							'pirepStatus' 	   => '1');	// Pirep Filed!							
					}
					else
					{
						$params = array(
							'pirepStatus' 	   => '2');	// Please Try Again!							
						
					}
					$send = $this->sendXML($params);						
					
					break;	
				
				case 'aircraft':
					
					$this->getAllAircraft();
					break;
					
				case 'schedules':
					self::getAllSchedules($pilotinfo->ranklevel, $xml->sch->ICAO);
				break;
				
				case 'airport':
					self::getAllAirports();
				break;
				
				case 'removebid';
					$pilotid = PilotData::parsePilotID($xml->verify->pilotID);
					$res = self::DeleteBids($pilotid);
					$params = array('delete'  => $res);
					$send = $this->sendXML($params);
				break;
				
				case 'schbyac':
					self::getAllSchedulesAC($pilotinfo->ranklevel, $xml->sch->ICAO);
				break;
				
				case 'schbyday':
					self::getSchByDay($pilotinfo->ranklevel);
				break;
				
				case 'cargo':
					self::getSchCargo($pilotinfo->ranklevel);
				break;
			}
			
		}
	}
	
	public function ConvertMinutes2Hours($Minutes)
	{
		if ($Minutes < 0)
		{
			$Min = Abs($Minutes);
		}
		else
		{
			$Min = $Minutes;
		}
		$iHours = Floor($Min / 60);
		$Minutes = ($Min - ($iHours * 60)) / 100;
		$tHours = $iHours + $Minutes;
		if ($Minutes < 0)
		{
			$tHours = $tHours * (-1);
		}
		$aHours = explode(".", $tHours);
		$iHours = $aHours[0];
		if (empty($aHours[1]))
		{
			$aHours[1] = "00";
		}
		$Minutes = $aHours[1];
		if (strlen($Minutes) < 2)
		{
			$Minutes = $Minutes ."0";
		}
		$tHours = $iHours .":". $Minutes;
		return $tHours;
	}
	
	public function sendXML($params)
	{
		$xml = new SimpleXMLElement("<sitedata />");
		
		$info_xml = $xml->addChild('info');
		foreach($params as $name => $value)
		{
			$info_xml->addChild($name, $value);
		}
		
		header('Content-type: text/xml'); 		
		$xml_string = $xml->asXML();
		echo $xml_string;
		
		
		return;	
	}
		
	public function getAllAircraft()
	{
		$results = OperationsData::getAllAircraft(true);	
		$xml = new SimpleXMLElement("<aircraftdata />");		
		$info_xml = $xml->addChild('info');		
		foreach($results as $row)
		{
			$info_xml->addChild('aircraftICAO', $row->icao);
			$info_xml->addChild('aircraftReg', $row->registration);
		}		
		header('Content-type: text/xml');
		echo $xml->asXML();
	}	
	
	public static function getAllSchedules($ranklevel, $icao) 
	{
		$params['s.depicao'] = $icao;
		$params['s.enabled'] = 1;
		$results = SchedulesData::findSchedules($params); 
		$xml = new SimpleXMLElement("<scheduledata />");
			if($results) {				
				foreach($results as $row) {
				if ($row->aircraftlevel <= $ranklevel) continue;        
				if(DISABLE_SCHED_ON_BID && $row->bidid != 0) continue;
				$info_xml = $xml->addChild('schedule');
				$info = OperationsData::getAircraftByReg($row->registration);
				$info_xml->addChild('id', $row->id);
				$info_xml->addChild('flightnumber', $row->code.$row->flightnum);
				$info_xml->addChild('aircraft', $info->icao);
				$info_xml->addChild('depicao', $row->depicao);
				$info_xml->addChild('depname', $row->depname);  
				$info_xml->addChild('arricao', $row->arricao);
				$info_xml->addChild('arrname', $row->arrname);
				$info_xml->addChild('flighttime', $row->flighttime);
				$info_xml->addChild('deptime', $row->deptime);
				$info_xml->addChild('arrtime', $row->arrtime);
				}
			}  
		header('Content-type: text/xml');  
		echo $xml->asXML();
	}

	public static function getAllAirports() 
	{
		$results = OperationsData::getAllAirports();
		$xml = new SimpleXMLElement("<airportdata />");
		$info_xml = $xml->addChild('info');
			foreach($results as $row) {
			$info_xml->addChild('airportICAO', $row->icao);
			$info_xml->addChild('airportName', $row->name);
			}
		header('Content-type: text/xml');  
		echo $xml->asXML();
	}

	public function DeleteBids($pilotid) 
	{
		$pilotid = DB::escape($pilotid);
		$row = DB::get_row('SELECT bidid as bidsid FROM '.TABLE_PREFIX.'bids WHERE pilotid='.$pilotid);
		if(count($row) == 1)
					{		
						$bidsid = $row->bidsid;	
					}	
		$res = SchedulesData::removeBid($bidsid);
		return $res;
		
	}
	
	public static function getAllSchedulesAC($ranklevel, $icao) 
	{
		$params['a.icao'] = $icao;
		$params['s.enabled'] = 1;
		$results = SchedulesData::findSchedules($params); 
		$xml = new SimpleXMLElement("<scheduledata />");
			if($results) {				
				foreach($results as $row) {
				if ($row->aircraftlevel <= $ranklevel) continue;        
				if(DISABLE_SCHED_ON_BID && $row->bidid != 0) continue;
				$info_xml = $xml->addChild('schedule');
				$info = OperationsData::getAircraftByReg($row->registration);
				$info_xml->addChild('id', $row->id);
				$info_xml->addChild('flightnumber', $row->code.$row->flightnum);
				$info_xml->addChild('aircraft', $info->icao);
				$info_xml->addChild('depicao', $row->depicao);
				$info_xml->addChild('depname', $row->depname);  
				$info_xml->addChild('arricao', $row->arricao);
				$info_xml->addChild('arrname', $row->arrname);
				$info_xml->addChild('flighttime', $row->flighttime);
				$info_xml->addChild('deptime', $row->deptime);
				$info_xml->addChild('arrtime', $row->arrtime);
				}
			}  
		header('Content-type: text/xml');  
		echo $xml->asXML();
	}
	
	public static function getSchByDay($ranklevel) 
	{
		$params['s.enabled'] = 1;
		$results = SchedulesData::findSchedules($params); 
		$xml = new SimpleXMLElement("<scheduledata />");
			if($results) {
				foreach($results as $row) {
				if ($row->aircraftlevel <= $ranklevel) continue;
				$row->daysofweek = str_replace('7', '0', $row->daysofweek);
				if(strpos($row->daysofweek, date('w')) === false) continue;
				$info_xml = $xml->addChild('schedule');
				$info = OperationsData::getAircraftByReg($row->registration);
				$info_xml->addChild('id', $row->id);
				$info_xml->addChild('flightnumber', $row->code.$row->flightnum);
				$info_xml->addChild('aircraft', $info->icao);
				$info_xml->addChild('depicao', $row->depicao);
				$info_xml->addChild('depname', $row->depname);  
				$info_xml->addChild('arricao', $row->arricao);
				$info_xml->addChild('arrname', $row->arrname);
				$info_xml->addChild('flighttime', $row->flighttime);
				$info_xml->addChild('deptime', $row->deptime);
				$info_xml->addChild('arrtime', $row->arrtime);
				}
			}  
		header('Content-type: text/xml');  
		echo $xml->asXML();
	}
 
 	public static function getSchCargo($ranklevel) 
	{
		$params['s.enabled'] = 1;
		$params['s.flighttype'] = 'C';
		$results = SchedulesData::findSchedules($params); 
		$xml = new SimpleXMLElement("<scheduledata />");
			if($results) {
				foreach($results as $row) {
				if ($row->aircraftlevel <= $ranklevel) continue;
				$info_xml = $xml->addChild('schedule');
				$info = OperationsData::getAircraftByReg($row->registration);
				$info_xml->addChild('id', $row->id);
				$info_xml->addChild('flightnumber', $row->code.$row->flightnum);
				$info_xml->addChild('aircraft', $info->icao);
				$info_xml->addChild('depicao', $row->depicao);
				$info_xml->addChild('depname', $row->depname);  
				$info_xml->addChild('arricao', $row->arricao);
				$info_xml->addChild('arrname', $row->arrname);
				$info_xml->addChild('flighttime', $row->flighttime);
				$info_xml->addChild('deptime', $row->deptime);
				$info_xml->addChild('arrtime', $row->arrtime);
				}
			}  
		header('Content-type: text/xml');  
		echo $xml->asXML();
	}

 
}