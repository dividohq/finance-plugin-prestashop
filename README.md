# Divido Prestashop

# Installation

For manual installation of the most recent version of the plugin follow all steps.
To install the plugin direct from prestashop start at step 5.

1. Download the Module from our github page our latest released version of the plugin can be found here.
2. Upload the dividopayment folder to your modules folder - as the web server user.
3. Go to Modules > Modules & Services > Search for "Divido"
** if the module doesn't show up straight away - you may need to clear your prestashop cache, or check file permissions **
4. Click Install > Click Configure.
![Search for Divido](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/2_search_divido.png)
# Configuration

Initial configuration out of the box simply needs your API key, this is provided by your client success manager or through your backoffice login.
1. Enter your api key and hit save.
![Api key example](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/3_add_api.png)
For testing be sure to use your sandbox api key.
2. Be sure to confirm that the default options selected for the payment statuses will work with your store workflow.
![Default configuration](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/4_configuration.png)

# Getting Started

## Title
The title allows you to adjust what gets displayed on the checkout page.

## Payment Page Description
The payment page description allows you to enter additional details to explain the process to your customers before they checkout.
![Payment page title and description example](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/title_description.png)

## Automatic Activation
For automatic activation you can select at what status an activation request will be set - by default this will be set on delivered.

## Plan Selection
You can manually adjust what plans you want available in your store globally from here.

## Widget on product page
The plugin ships with a widget built in that will display an example small widget on your product page.
![Product Page widget](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/product_page_widget_expanded.png)

## Calculator on product page
The calculator is a large form version of the small widget.
![Product page calculator](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/product_page_calculator.png)
## Prefix & Suffix
The prefix and suffix fields allow you to adjust the text available on the small widget.
![Prefix suffix and product page widget](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/product_page_widget_prefix_suffix.png)

## Require whole cart to be available
If some items in your cart are not available on finance - the whole basket will be disallowed.

## Cart amount minimum
Divido will not be available on the checkout if the cart is below this amount.

## Product Selection
Define if divido is available on all products. Selected Products, or products above a certain price.


### Selected Products Options

You can override specific plans on a product by product basis, for instance if you have a range of products where you only want to display 9.9% plans.
1. Go to the product page.
![Go to individual product page](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/5_proudct_page.png)
2. Scroll across to modules.
![Select divido from modules](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/6_product_module.png)
3. Select Divido as the module to configure.
4. Choose available for finance and then select the individual or multiple plans you want accessible for that product.
![Choose your plans](https://s3-eu-west-1.amazonaws.com/content.divido.com/images/documentation/Prestashop/7_plans.png)

## Divido Response Mapping

There are a number of statuses that come back from Divido during the order process, we have tried to make them fit a generic workflow out of the box.
This will let you adjust responses and updates based on your unique workflow.

# Reporting Issues

For any bugs or issues please raise a ticket here on github with the following information:
Your version of prestashop,
The version of the plugin,
A small description of the steps to reproduce the error,
Any error logs or notices generated.

If you have a security concern or bug relating to this module please email jonathan.carter@divido.com directly with details.

# Contributing

We welcome all contributions - if you want to help out or get involved, please review our code of conduct and contribution guidelines.

 
 # Maintainer

https://github.com/DividoFinancialServices/

https://github.com/JonnyCarter/

 # Contributors

https://github.com/ChilliApple