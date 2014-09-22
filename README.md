#phpSLSP
Simple php wrapper for Slovenska Sporitelna's [databanking API](http://developer.databanking.sk/)

Current features:
-Login
-Get account list(or account by IBAN)
-Select account
-Get transaction list
-Get transaction (by date range, variable symbol,[amount])
-Logout

##Instructions
-Fill in config-dist.php
-rename to config.php
-short demo folows, read the file for details
```
$s=new SLSP();
if(!$s->login()){die();}//login
$s->selectAccount($s->getAccountByIBAN("yourIBAN"));
$t=$s->getTransaction(time()-86400*10,"13121312");($after_unix_timestamp,$variable_symbol)
echo $t->amount; //class SLSPTransaction in SLSP.php
echo $s->getAccountByIBAN("yourIBAN")->own_resources; //class SLSPAccount in SLSP.php
```

##TODO:
-Safer cookie handling (currently unencrypted file)
-Implement payments
-Add demo
