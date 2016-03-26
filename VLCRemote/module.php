<?php                                                                           
    class VLCRemote extends IPSModule
    {
        /**
         * Log Message
         * @param string $Message
         */
        protected function Log($Message)
        {
            IPS_LogMessage(__CLASS__, $Message);
        }

        /**
         * Create
         */         
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            
            $this->RegisterPropertyBoolean("Active", false);
            if (strncasecmp(PHP_OS, 'WIN', 3) == 0)  
            {
                $this->RegisterPropertyString("Path", "C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe");
            }
            else
            {
                $this->RegisterPropertyString("Path", "/usr/bin/vlc");
            }            
            $this->RegisterPropertyString("IPAddress", "localhost");
            $this->RegisterPropertyInteger("Port", 80);
            $this->RegisterPropertyString("Password", "IPS");
            $this->RegisterPropertyInteger("Poller", 1000);
            $this->RegisterTimer("Poller", 0, "VLCRemote_Update(\$_IPS['TARGET'], \"\");");   
            $this->CreateIntegerProfile("Volume", "Intensity", "", " %", 0, 100, 1);
            $this->CreateBooleanProfile("Shuffle", "Shuffle", "", "");
            $this->CreateBooleanProfile("Repeat", "Repeat", "", "");
            $this->CreateIntegerProfile("PlayerStatus", "Information", "", "", 0, 4, 0);
            IPS_SetVariableProfileAssociation("PlayerStatus", 0, "Prev", "", -1);
            IPS_SetVariableProfileAssociation("PlayerStatus", 1, "Play", "", -1);
            IPS_SetVariableProfileAssociation("PlayerStatus", 2, "Pause", "", -1);
            IPS_SetVariableProfileAssociation("PlayerStatus", 3, "Stop", "", -1);
            IPS_SetVariableProfileAssociation("PlayerStatus", 4, "Weiter", "", -1);
        }

        /**
         * ApplyChanges
         */
        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();      
            
            $this->RegisterVariableString("TrackPos", "Titelposition", "", 1);
            $this->RegisterVariableString("TrackLen", "Titellänge", "", 2);
            $this->RegisterVariableString("TrackArtist", "Artist", "", 3);
            $this->RegisterVariableString("TrackTitle", "Titel", "", 4);
            $this->RegisterVariableInteger("Volume", "Volume", "Volume", 5);
            $this->EnableAction("Volume");
            $this->RegisterVariableBoolean("Shuffle", "Zufall", "Shuffle", 6);
            $this->EnableAction("Shuffle");
            $this->RegisterVariableBoolean("Repeat", "Wiederholen", "Repeat", 7);
            $this->EnableAction("Repeat");
            $this->RegisterVariableInteger("Status", "Status", "PlayerStatus", 8);            
            $this->EnableAction("Status");
            $this->SetTimerInterval("Poller", $this->ReadPropertyInteger("Poller"));
        }   
  
        /**
         * RequestAction
         * @param string $Ident
         * @param type $Value
         */
        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) 
            {
                case "Volume":
                    $this->SetVolume($Value);
                break;
                case "Shuffle":
                    $this->SetShuffle($Value);
                break;
                case "Repeat":
                    $this->SetRepeat($Value);
                break;
                case "Status":
                    switch ($Value) 
                    {
                        case 0: $this->Prev();  break;
                        case 1: $this->Play();  break;
                        case 2: $this->Pause(); break;
                        case 3: $this->Stop();  break;
                        case 4: $this->Next();  break;
                    }
                break;
            }
        }        
        
        /**
         * VLCRemote_Update();
         */
        public function Update($Command = "")
        {
            if ( $this->ReadPropertyBoolean("Active") ) 
            { 
                if (strncasecmp(PHP_OS, 'WIN', 3) == 0)  
                {
                    exec('tasklist -fi "imagename eq vlc.exe"', $out);
                    if(empty($out[3])) 
                    {
                        IPS_Execute($this->ReadPropertyString("Path"), "-I http --http-host=" . $this->ReadPropertyString("IPAddress") . " --http-port=" . $this->ReadPropertyInteger("Port") . " --http-password=". $this->ReadPropertyString("Password"), false, false); 
                    }                    
                }
                else
                {
                    exec("pgrep vlc", $pids);
                    if(empty($pids)) 
                    {
                        IPS_Execute($this->ReadPropertyString("Path"), "-I http --http-host " . $this->ReadPropertyString("IPAddress") . " --http-port " . $this->ReadPropertyInteger("Port") . " --http-password ". $this->ReadPropertyString("Password"), false, false); 
                    }                    
                }                           
            }
            if ( !@Sys_Ping($this->ReadPropertyString("IPAddress"), 1000) )
            {
                $this->SetStatus(201);                 
                #trigger_error("Invalid IP-Address", E_USER_ERROR);
                exit;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->ReadPropertyString("IPAddress") . ":" . $this->ReadPropertyInteger("Port") ."/requests/status.xml?command=" . $Command);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, ":" . $this->ReadPropertyString("Password"));                
            $xmlData = curl_exec($ch);
            curl_close($ch);
            #echo $xmlData;
            libxml_use_internal_errors(true);
            $dom = new DOMDocument("1.0", "UTF-8");
            $dom->strictErrorChecking = false;
            $dom->validateOnParse = false;
            $dom->recover = true;            
            @$dom->loadXML($xmlData);
            $xml = @simplexml_import_dom($dom);
            libxml_clear_errors();
            libxml_use_internal_errors(false);  
            if(empty($xml))
            {
                $this->SetStatus(202);                 
                exit;
            }        
            $this->SetStatus(102);
            $this->SetValue($this->GetIDForIdent("TrackPos"), gmdate("H:i:s", (integer)$xml->time));
            $this->SetValue($this->GetIDForIdent("TrackLen"), gmdate("H:i:s", (integer)$xml->length));
            foreach($xml->information->category[0]->children() as $child) 
            {
                switch ($child->attributes()) 
                {
                    case "filename": $filename = (string)$child; break;
                    case "artist":   $artist = (string)$child; break;
                    case "title":    $title = (string)$child; break;
                }                
            }
            $this->SetValue($this->GetIDForIdent("TrackArtist"), @$artist);          
            $this->SetValue($this->GetIDForIdent("TrackTitle"), @$title);
            $this->SetValue($this->GetIDForIdent("Volume"), (int)($xml->volume / 2.56));
            if($xml->random == "true")
            {
                $this->SetValue($this->GetIDForIdent("Shuffle"), true);
            }
            else
            {
                $this->SetValue($this->GetIDForIdent("Shuffle"), false);
            }
            if($xml->repeat == "true")
            {
                $this->SetValue($this->GetIDForIdent("Repeat"), true);
            }
            else
            {
                $this->SetValue($this->GetIDForIdent("Repeat"), false);
            }            
            switch ($xml->state) 
            {
                case "playing":
                    $this->SetValue($this->GetIDForIdent("Status"), 1);   
                break;
                case "paused":
                    $this->SetValue($this->GetIDForIdent("Status"), 2);   
                break;
                case "stopped":
                    $this->SetValue($this->GetIDForIdent("Status"), 3);   
                break;
            }                        
        }
        
        /**
         * VLCRemote_AddFile
         * @param string $Path
         */
        public function AddFile($Path) 
        {
            if(file_exists($Path))
            {
                $Path = "file:///" . $Path;
            }
            $Path = str_replace(" ", "%20", $Path);
            $Path = urlencode($Path);
            $this->Update("in_play&input=" . $Path);
        }
  
        /**
         * VLCRemote_ClearPlaylist
         */
        public function ClearPlaylist()
        {
            $this->Update("pl_empty");
        }        
        
        /**
         * VLCRemote_Next
         */
        public function Next()
        {
            $this->Update("pl_next");
        }

        /**
         * VLCRemote_Pause
         */
        public function Pause()
        {
            if(GetValue($this->GetIDForIdent("Status")) != 2)
            {
                $this->Update("pl_pause");
            }
        }        
 
        /**
         * VLCRemote_Play
         */
        public function Play()
        {
            if(GetValue($this->GetIDForIdent("Status")) != 1)
            {
                $this->Update("pl_pause");
            }
        }       
        
        /**
         * VLCRemote_PlayFile
         * @param string $Path
         */
        public function PlayFile($Path)
        {
            $this->ClearPlaylist();
            $this->AddFile($Path);
        }
        
        
        /**
         * VLCRemote_Prev
         */
        public function Prev()
        {
            $this->Update("pl_previous");
        }

        /**
         * VLCRemote_SetRepeat
         */
        public function SetRepeat($DoRepeat)
        {
            if(GetValue($this->GetIDForIdent("Repeat")) != $DoRepeat)
            {
                $this->Update("pl_repeat");
            }
        }        
        
        /**
         * VLCRemote_SetShuffle
         */
        public function SetShuffle($DoShuffle)
        {
            if(GetValue($this->GetIDForIdent("Shuffle")) != $DoShuffle)
            {
                $this->Update("pl_random");
            }
        }
        
        /**
         * VLCRemote_SetVolume
         * @param integer $Volume
         */
        public function SetVolume($Volume)
        {
            $this->Update("volume&val=" . ($Volume * 2.56));
        }         
        
        /**
         * VLCRemote_Stop
         */
        public function Stop()
        {
            $this->Update("pl_stop");
        }        
        
        /**
         * SetValue
         * @param integer $ID
         * @param type $Value
         */
        private function SetValue($ID, $Value)
        {
            if ( GetValue($ID) !== $Value ) { SetValue($ID, $Value); }
        }
        
        /**
         * CreateBooleanProfile
         * @param string $ProfileName
         * @param string $Icon
         * @param string $Präfix
         * @param string $Suffix
         */
        private function CreateBooleanProfile($ProfileName, $Icon, $Präfix, $Suffix)
        {
            $Profile = IPS_VariableProfileExists($ProfileName);
            if ($Profile === FALSE)
            {
                IPS_CreateVariableProfile($ProfileName, 0);
                IPS_SetVariableProfileIcon($ProfileName,  $Icon);
                IPS_SetVariableProfileText($ProfileName, $Präfix, $Suffix);
                #IPS_SetVariableProfileAssociation($ProfileName, $Wert, $Name, $Icon, $Farbe);
            }
        }        
        
        /**
         * CreateIntegerProfile
         * @param string $ProfileName
         * @param string $Icon
         * @param string $Präfix
         * @param string $Suffix
         * @param integer $MinValue
         * @param integer $MaxValue
         * @param integer $StepSize
         */
        private function CreateIntegerProfile($ProfileName, $Icon, $Präfix, $Suffix, $MinValue, $MaxValue, $StepSize)
        {
            $Profile = IPS_VariableProfileExists($ProfileName);
            if ($Profile === FALSE)
            {
                IPS_CreateVariableProfile($ProfileName, 1);
                IPS_SetVariableProfileIcon($ProfileName,  $Icon);
                IPS_SetVariableProfileText($ProfileName, $Präfix, $Suffix);
                IPS_SetVariableProfileValues($ProfileName, $MinValue, $MaxValue, $StepSize);
                #IPS_SetVariableProfileAssociation($ProfileName, $Wert, $Name, $Icon, $Farbe);
            }
        }
    }