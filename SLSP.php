<?php
/*
*  wrapper for slsp api, based loosely on github.com/hecko/transparentnyucet sync
*/

require_once("config.php");
class SLSP{
	private $cookiefile;
	private $account_list;
	private $loged_in;
	/**
	* create session
	*/
	public function __construct(){
		$this->cookiefile=tempnam(TEMPDIR,"slsp-");
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/ibxindex.xml");
		curl_setopt($c, CURLOPT_COOKIEJAR, $this->cookiefile);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_LOW_SPEED_LIMIT, 1);
		curl_setopt($c, CURLOPT_LOW_SPEED_TIME, 30);
		$output = curl_exec ($c);
	}

	/**
	* login, return true on success
	*/	
	
	public function login(){	
		$data = array('user_id' => SLSP_CLIENTID,'tap'=>'2','pwd' => SLSP_PASSWORD,'lng2'=>'en');
		$post = http_build_query($data, '', '&');
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/login/ibxlogin.xml");
		curl_setopt ($c, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $post);
		$output = curl_exec ($c);
		$res=simplexml_load_string($output);
		if($res->error){$this->loged_in=false;return false;}
		else{$this->loged_in=true;return true;}
	}
	/**
	* Get account list (array of SLSPAccounts)
	*/
	public function getAccountList(){
		if(!$this->loged_in){$this->login();}
		if($this->account_list){return $this->account_list;}		
		$c = curl_init();
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/accounts/ibxaccounts.xml");
		curl_setopt ($c, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec ($c);
		//echo $output;
		error_reporting(E_ALL);
		$xml = simplexml_load_string($output);
		$acc_list=array();
		//var_dump($xml->result->{'reply-account-list'});
		foreach($xml->result->{'reply-account-list'}->{'account-data'} as $ac){
			$a=new SLSPAccount();
			$a->id=$ac->{'account-id'};
			$a->iban=$ac->{'account-iban'};
			$a->type=$ac->{'account-type'};
			$a->name=$ac->{'account-name'};
			$a->prefix=$ac->{'account-prefix'};
			$a->number=$ac->{'account-number'};
			$a->currency=$ac->{'currency'};
			$a->balance=$ac->{'balance'};
			$a->disp_balance=$ac->{'disponible-balance'};
			$a->last_turnover=$ac->{'last-turnover'};
			$a->own_resources=$ac->{'own_resources_balance'};
			$acc_list["".$ac->{'account-iban'}]=$a;					
		}
		$this->account_list=$acc_list;
		return $acc_list;
	}
	
	/**
	* returns SLSPAccount from iban, logs in and loads account list if neccessary
	*/	
	public function getAccountByIBAN($iban){
		if(!$this->loged_in){$this->login();}
		if(!$this->account_list){$this->getAccountList();}
		if(isset($this->account_list[$iban])){
			return $this->account_list[$iban];		
		}else{return False;}
	}	

	/**
	* select account
	*/
	public function selectAccount(SLSPAccount $account){
		if(!$this->loged_in){$this->login();}		
		$c = curl_init();		
		$data = array("uid" => "".$account->id,'utyp' => "".$account->type,'ucis' => "".$account->number,'uprcis' => "".$account->prefix);//turn data into strings so http_build_query doesn't add []
		$post = http_build_query($data, '', '&');
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/accinfo/ibxaccinfo.xml");
		curl_setopt ($c, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $post);
		$output = curl_exec ($c);	
		$xml=simplexml_load_string($output);
		if($xml->result->ok!=NULL){return true;}
		else{return false;}
	}	
	
	public function logout(){
		$c = curl_init();		
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/logout/ibxlogoutyes.xml ");
		curl_setopt ($c, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);	
		$output = curl_exec ($c);	
	}
	
}
	class SLSPAccount{
		public $id;
		public $type;
		public $iban;
		public $name;
		public $prefix;
		public $number;
		public $currency;	
		public $balance;
		public $disp_balance;
		public $own_resources;
		public $last_turnover;
	}

