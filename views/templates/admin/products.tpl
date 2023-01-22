{*
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $success neq ''}
	<div class="module_confirmation conf confirm alert alert-success">
		<button type="button" class="close" data-dismiss="alert">×</button>
		{$success|escape:'html':'utf-8'}
	</div>
{/if}
{if $warning neq ''}
	<div class="module_confirmation conf confirm alert alert-warning">
		<button type="button" class="close" data-dismiss="alert">×</button>
		{$warning|escape:'html':'utf-8'}
	</div>
{/if}
<div class="row">
	<div class="panel col-lg-12">
		<div class="panel-heading">
			List Products
		</div>
		<form method="post" action="index.php?controller=AdminProductsWoocommerce&token={$token}" id="form-product">
		<div class="table-responsive-row clearfix">
				<table id="table-wk_mp_seller_product" class="table wk_mp_seller_product">
					<thead>
						<tr class="nodrag nodrop">
							<th class="center fixed-width-xs"></th>
							<th class="">
								<span class="title_box active">ID</span>
							</th>
							<th class="">
								<span class="title_box">Product Name</span>
							</th>
							<th class="">
								<span class="title_box">Product Reference</span>
							</th>
							<th class="">
								<span class="title_box">Price</span>
							</th>
						</tr>
					</thead>
					<tbody>
						
						{foreach $data as $single_product}
						<tr class=" odd">
							<td class="row-selector text-center" style="padding:10px;">
								<input type="checkbox" name="product_id[]" value="{$single_product['id']}" class="noborder">
							</td>
							<td class="pointer">{$single_product['id']}</td>
							<td class="pointer">{$single_product['name']}</td>
							<td class="pointer">{$single_product['reference']}</td>
							<td class="pointer">{Tools::displayPrice($single_product['price'])}</td>
						</tr>
						{/foreach}
					</tbody>
				</table>
			
		</div>
		<div class="row">
			<div class="col-lg-6">
				<div class="btn-group bulk-actions dropup">
					<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" id="bulk_action_menu_wk_mp_seller_product">
						Bulk actions <span class="caret"></span>
					</button>
					<ul class="dropdown-menu">
						<li>
							<a href="#" onclick="javascript:checkDelBoxes($(this).closest('form').get(0), 'product_id[]', true);return false;">
								<i class="icon-check-sign"></i>&nbsp;Select all
							</a>
						</li>
						<li>
							<a href="#" onclick="javascript:checkDelBoxes($(this).closest('form').get(0), 'product_id[]', false);return false;">
								<i class="icon-check-empty"></i>&nbsp;Unselect all
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<input type="hidden" name="token" value="{$token}">
		<button type="submit" name="submit" value="submit" class="btn btn-default" data-list-id="product_id" style="float:right;">
									Send To Woocommerce
								</button>
		</form>
	</div>
</div>
	