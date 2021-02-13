{**
 * 2017 Joia Software Solutions
 *
 * NOTICE OF LICENSE
 *
 *  @author Joia Software Solutions srl <info@joiasoftware.it>
 *  @copyright  Joia Software Solutions
 *  @license commercial
 *}
 
<div class="panel">
	<h3><i class="icon-cogs"></i> {l s='Export Configurations' mod='tshopexport'}</h3>
	<div id="indexing-warning" class="alert alert-warning" style="display: none">
		{l s='Exporting is in progress. Please do not leave this page' mod='tshopexport'}
	</div>
	<div class="row">
		<div class="alert alert-info col-xs-6">
			{l s='Extract your Prestashop\'s data into a specific directory to import in T-SHOP ' mod='tshopexport'}
			<br />
			{*$tree*}
		</div>
		<div class="col-xs-6">
			<p>
				<a id="joiaexport" class="ajaxcall-recurcive btn btn-default" href="#">{l s='Export Data' mod='tshopexport'}</a>
			</p>
		</div>
	</div>
</div>

{addJsDef export_url=$export_url|escape:'quotes':'UTF-8'}
{addJsDef export_message=$export_message|escape:'quotes':'UTF-8'}
