<?php
/*
*  wrapper for slsp api, based loosely on github.com/hecko/transparentnyucet sync
*/
require_once("config.php");
class SLSP{
	private $cookiefile;
	private $account_list;
	private $loged_in;
	private $transaction_list=array();
	private $transaction_list_from;
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
			$a->id=s($ac->{'account-id'});
			$a->iban=s($ac->{'account-iban'});
			$a->type=s($ac->{'account-type'});
			$a->name=s($ac->{'account-name'});
			$a->prefix=s($ac->{'account-prefix'});
			$a->number=s($ac->{'account-number'});
			$a->currency=s($ac->{'currency'});
			$a->balance=s($ac->{'balance'});
			$a->disp_balance=s($ac->{'disponible-balance'});
			$a->last_turnover=s($ac->{'last-turnover'});
			$a->own_resources=s($ac->{'own_resources_balance'});
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
		
	/**
	* Verifies transaction exists, returns Transaction or false if no transaction satisfies the criteria
	* use amount=-1 for any amount
	*/
	public function getTransaction($after,$variable_symb,$amount=-1,$before=-1){
		if($before==-1){$before=time();}
		if(!$this->transaction_list||$this->transaction_list_from>$after){
			$this->getTransactionList($after-100);//load list
		}
		foreach($this->transaction_list as $transaction){
			if($after<$transaction->maturity_date&&$transaction->maturity_date<$before&&$transaction->variable_symb==$variable_symb&&($transaction->amount==$amount||$amount==-1)){
				return $transaction;
			}
		}
		return false;
	}	
	
	/**
	* @params $from_date -> timestamp from which to get list
	* @return array of SLSPTransactions from current account	
	*/	
	public function getTransactionList($from_date){
		$this->transaction_list_from=$from_date;
		$datum_od=date("j.m.Y",$from_date);
		$c = curl_init();
		$data = array(
			'no_f_no_od' => $datum_od, //datum od
			'no_s_how_much' => 'showall', //vsetky zaznamy naraz
			'no_s_amounts' => 'amntnone',
			);
		$post = http_build_query($data, '', '&');
		curl_setopt($c, CURLOPT_URL, "https://ib.slsp.sk/ebanking/accto/ibxtofilter.xml");
		curl_setopt ($c, CURLOPT_COOKIEFILE, $this->cookiefile);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_POST, true);
		curl_setopt($c, CURLOPT_POSTFIELDS, $post);
		$output = curl_exec ($c);
		$xml = simplexml_load_string($output);
		//echo $output; die();
		foreach($xml->result->turnovers->turnover as $t){
			$a=new SLSPTransaction();
			$a->amount=doubleval($t->amount);
			$a->maturity_date=strtotime(s($t->{'maturity-date'}));
			$a->id=s($t->attributes()->transid);
			$a->note=s($t->note);
			$a->currency=s($t->currency);
			$a->spec_symb=s($t->{'spec-symb'});
			$a->variable_symb=s($t->{'variable-symb'});
			$a->constant_symb=s($t->{'spec-symb'});
			$a->storno=s($t->storno);
			$a->counter_prefix=s($t->{'counter-prefix'});
			$a->counter_account=s($t->{'counter-account'});
			$a->counter_name=s($t->{'counter-name'});
			$a->counter_bank=s($t->{'counter-bank'});
			$a->type=s($t->type);
			$this->transaction_list[]=$a;
		}
	return $this->transaction_list;	
	}
	
}

	class SLSPTransaction{
		public $id;
		public $amount;
		public $maturity_date;
		public $counter_prefix;
		public $counter_account;
		public $counter_name;
		public $counter_bank;
		public $constant_symb;
		public $variable_symb;
		public $spec_symb;
		public $note;
		public $type;
		public $currency;
		public $storno;
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
function s($a){return "".$a;}//stringify
