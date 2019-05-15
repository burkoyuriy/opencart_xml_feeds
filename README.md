# OPENCART 2.3.x feed generator
## XML выгрузка товаров на Hotline.ua, price.ua и e-catalog
Module for generate xml feeds for hotline, price.ua and e-catalog

A small module for creating upload files on the aggregator site (hotline, price.ua, e-catalog).
The module was used to quickly solve the tasks and was loaded as is.
The module generates XML feeds and requires additional configuration for a specific store. It can serve as a starting point for creating a more functional module.

#### Opencart version 2.3.

In the file admin/controller/extension/module/allxml.php, you can set the desired upload parameters:
- Image size ($image_width, $image_height)
- Store address ($store_url)
- Aliases to replace category names ($categories_aliases). Used for hotline
- Which categories should not be unloaded ($ignore [category id])
- Path to save files ($path)


#### To generate feeds:
- $store_url/export/allxml.php?target=price
- $store_url/export/allxml.php? target=ecatalog
- $store_url/export/allxml.php? target=hotline?storeid=your_store_id

#### Default path for saving files:
- https://store_url/export/file/*.xml

#### Generated feeds are available at:
- https://store_url/export/file/feed_priceua.xml
- https://store_url/export/file/feed_hotline.xml
- https://store_url/export/file/feed_ecatalog.xml
