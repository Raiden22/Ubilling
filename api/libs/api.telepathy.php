<?php

/*
 * Base class prophetic guessing login by the address/surname/realname
 */

class Telepathy {

    /**
     * Contains system alter config as key=>value
     *
     * @var array
     */
    protected $altCfg = array();

    /**
     * Contains all available user address
     *
     * @var array
     */
    protected $alladdress = array();

    /**
     * Contains all available users realnames
     *
     * @var array
     */
    protected $allrealnames = array();

    /**
     * Contains preprocessed users surnames
     *
     * @var array
     */
    protected $allsurnames = array();

    /**
     * Contains all available user mobiles
     *
     * @var array
     */
    protected $allMobiles = array();

    /**
     * Contains all available additional user mobiles
     *
     * @var array
     */
    protected $allExtMobiles = array();

    /**
     * Contains all available user phones
     *
     * @var array
     */
    protected $allPhones = array();

    /**
     * Case sensitivity flag
     *
     * @var bool
     */
    protected $caseSensitive = false;

    /**
     * Cached address usage flag
     *
     * @var bool
     */
    protected $cachedAddress = true;

    /**
     * City display flag
     *
     * @var array
     */
    protected $citiesAddress = false;

    /**
     * Creates new telepathy instance
     * 
     * @param bool $caseSensitive
     * @param bool $cachedAddress
     * @param bool $citiesAddress
     * 
     * @return void
     */
    public function __construct($caseSensitive = false, $cachedAddress = true, $citiesAddress = false) {
        $this->caseSensitive = $caseSensitive;
        $this->cachedAddress = $cachedAddress;
        $this->citiesAddress = $citiesAddress;
        $this->loadConfig();
        $this->loadAddress();

        if (!$this->caseSensitive) {
            $this->addressToLowerCase();
        }
        if (!empty($this->alladdress)) {
            $this->alladdress = array_flip($this->alladdress);
        }
    }

    /**
     * Loads system alter.ini config into protected property for further usage
     * 
     * @global object $ubillingConfig
     * 
     * @return void
     */
    protected function loadConfig() {
        global $ubillingConfig;
        $this->altCfg = $ubillingConfig->getAlter();
    }

    /**
     * Loads cached address data to private data property 
     * 
     * @return void
     */
    protected function loadAddress() {
        if (!$this->citiesAddress) {
            if ($this->cachedAddress) {
                $this->alladdress = zb_AddressGetFulladdresslistCached();
            } else {
                $this->alladdress = zb_AddressGetFulladdresslist();
            }
        } else {
            $this->alladdress = zb_AddressGetFullCityaddresslist();
        }
    }

    /**
     * Loads all user realnames from database into private prop
     * 
     * @return void
     */
    protected function loadRealnames() {
        $this->allrealnames = zb_UserGetAllRealnames();
    }

    /**
     * Loads all existing phone data into protected props for further usage
     * 
     * @return void
     */
    public function usePhones() {
        $allPhoneData = zb_UserGetAllPhoneData();
        if (!empty($allPhoneData)) {
            foreach ($allPhoneData as $login => $each) {
                $cleanMobile = vf($each['mobile'], 3);
                if (!empty($cleanMobile)) {
                    $this->allMobiles[$cleanMobile] = $login;
                }

                $cleanPhone = vf($each['phone'], 3);
                if (!empty($cleanPhone)) {
                    $this->allPhones[$cleanPhone] = $login;
                }
            }
        }
        //additional mobiles loading if enabled
        if ($this->altCfg['MOBILES_EXT']) {
            $extMob = new MobilesExt();
            $allExtTmp = $extMob->getAllMobilesUsers();
            if (!empty($allExtTmp)) {
                foreach ($allExtTmp as $eachExtMobile => $login) {
                    $cleanExtMobile = vf($eachExtMobile, 3);
                    $this->allExtMobiles[$cleanExtMobile] = $login;
                }
            }
        }
    }

    /**
     * Preprocess all user surnames into usable data
     * 
     * @return void
     */
    protected function surnamesExtract() {
        if (!empty($this->allrealnames)) {
            foreach ($this->allrealnames as $login => $realname) {
                $raw = explode(' ', $realname);
                if (!empty($raw)) {
                    $this->allsurnames[$login] = $raw[0];
                }
            }
        }
    }

    /**
     * external passive constructor for name realname login detection
     * 
     * @return void
     */
    public function useNames() {
        $this->loadRealnames();
        $this->surnamesExtract();

        if (!empty($this->allrealnames)) {
            $this->allrealnames = array_flip($this->allrealnames);
        }

        if (!empty($this->allrealnames)) {
            $this->allsurnames = array_flip($this->allsurnames);
        }
    }

    /**
     * preprocess available address data into lower case
     * 
     * @return void
     */
    protected function addressToLowerCase() {
        global $ubillingConfig;
        $alterconf = $ubillingConfig->getAlter();

        $cacheTime = $alterconf['ADDRESS_CACHE_TIME'];
        $cacheTime = time() - ($cacheTime * 60);
        if (!$this->citiesAddress) {
            $cacheName = 'exports/fulladdresslistlowercache.dat';
        } else {
            $cacheName = 'exports/fullcityaddresslistlowercache.dat';
        }
        $updateCache = false;
        if (file_exists($cacheName)) {
            $updateCache = false;
            if ((filemtime($cacheName) > $cacheTime)) {
                $updateCache = false;
            } else {
                $updateCache = true;
            }
        } else {
            $updateCache = true;
        }

        if (($alterconf['ADDRESS_CACHE_TIME']) AND ( $this->cachedAddress)) {
            if ($updateCache) {
                $tmpArr = array();
                if (!empty($this->alladdress)) {
                    foreach ($this->alladdress as $eachlogin => $eachaddress) {
                        $tmpArr[$eachlogin] = strtolower_utf8($eachaddress);
                    }
                    $this->alladdress = $tmpArr;
                    $tmpArr = array();
                }
                //store property to cache
                $cacheStoreData = serialize($this->alladdress);
                file_put_contents($cacheName, $cacheStoreData);
                $cacheStoreData = '';
            } else {
                $rawCacheData = file_get_contents($cacheName);
                $rawCacheData = unserialize($rawCacheData);
                $this->alladdress = $rawCacheData;
                $rawCacheData = array();
            }
        } else {
            $tmpArr = array();
            if (!empty($this->alladdress)) {
                foreach ($this->alladdress as $eachlogin => $eachaddress) {
                    $tmpArr[$eachlogin] = strtolower_utf8($eachaddress);
                }
                $this->alladdress = $tmpArr;
                $tmpArr = array();
            }
        }
    }

    /**
     * detects user login by its address
     * 
     * @param string $address address to guess
     * 
     * @return string
     */
    public function getLogin($address) {
        if (!$this->caseSensitive) {
            $address = strtolower_utf8($address);
        }

        if (isset($this->alladdress[$address])) {
            return ($this->alladdress[$address]);
        } else {
            return(false);
        }
    }

    /**
     * returns user login by surname
     * 
     * @param string $surname
     * 
     * @return string
     */
    public function getBySurname($surname) {
        if (isset($this->allsurnames[$surname])) {
            return ($this->allsurnames[$surname]);
        } else {
            return(false);
        }
    }

    /**
     * Get user login by some phone number
     * 
     * @param string $phoneNumber
     * @param bool $onlyMobile
     * 
     * @return string
     */
    public function getByPhone($phoneNumber, $onlyMobile = false) {
        $result = '';
        /**
         * Come with us speeding through the night
         * As fast as any bird in flight
         * Silhouettes against the Mother Moon
         * We will be there
         */
        if (!$onlyMobile) {
            if (!empty($this->allPhones)) {
                foreach ($this->allPhones as $baseNumber => $userLogin) {
                    if (ispos((string) $phoneNumber, (string) $baseNumber)) {
                        $result = $userLogin;
                    }
                }
            }
        }

        if (!empty($this->allExtMobiles)) {
            foreach ($this->allExtMobiles as $baseNumber => $userLogin) {
                if (ispos((string) $phoneNumber, (string) $baseNumber)) {
                    $result = $userLogin;
                }
            }
        }

        if (!empty($this->allMobiles)) {
            foreach ($this->allMobiles as $baseNumber => $userLogin) {
                if (ispos((string) $phoneNumber, (string) $baseNumber)) {
                    $result = $userLogin;
                    return ($result);
                }
            }
        }
        return ($result);
    }

}

?>