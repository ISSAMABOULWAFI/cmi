  

<!-- ERROR ON UPDATE -->
<!-- ./END ERROR ON UPDATE -->


<!--basic settings-->
<form action="{$cmi_form|escape:'htmlall':'UTF-8'}" method="post" style="margin-bottom:25px;">
   
    <div role="tabpanel">
        <div class="tab-content">
            <div id="cmi_step1" class="tab-pane active" role="tabpanel">
                <div class="panel">
                    <h4>Configurer la solution de paiement Cmi dans votre PrestaShop </h4>
                   
				   <div class="form-group">
                        <label style='text-align:left' for='actionslk'>l'URL de la Gateway de paiement </label> <sup>*</sup>
                        <input size="10" type="text" name="actionslk" value="{$actionslk}" class="inputBp" id="actionslk"/>
                    </div>
                    <div class="form-group">
                        <label style='text-align:left' for='merchid'>{l s='cmi merchant id' mod='cmi'}</label> <sup>*</sup>
                        <input size="10" type="text" name="merchid" value="{$merchid}" class="inputBp" id="merchid"/>
                    </div>
					<div class="form-group">
                        <label style='text-align:left' for='secretkey'>Clé de hachage</label> <sup>*</sup>
                        <input size="10" type="text" name="secretkey" value="{$secretkey}" class="inputBp" id="secretkey"/>
                    </div><div class="form-group">
                        <label style='text-align:left' for='secretkey'>Mode de confirmation des Transaction CMI</label> <sup>*</sup>
                        <select name="confirmation_mode" style="width:130px;" id="confirmation_mode">
                                <option value="1"{if $confirmation_mode == 1} selected="selected"{/if}>Automatique</option>
                                <option value="2"{if $confirmation_mode == 2} selected="selected"{/if}>Manuelle</option>
                        </select>
                    </div>
                   <hr>

                    <input class="btn btn-primary" name="submitCmi_config" value="{l s='Save' mod='cmi'}" type="submit" />
                </div>
            </div>
        </div>
    </div>
</form>
<p style="font-size:0.8em">
    {l s='This module has been developped by ' mod='cmi'}<a href="https://www.cmi.co.ma/">Cmi</a>
</p>