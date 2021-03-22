# DeepFashion Integration Plugin

## A demo plugin to prepare shop products and recommendation system with DeepFashion data set

## Requirements

| Version 	| Requirements               	|
|---------	|----------------------------	|
| 1.0.0    	| Shopware 6.3 >=	            |


# Installation

**1. Clone git Repositories**

```bash
cd development/custom/plugins

git clone <THIS REPOSITORY>

```

**2. Build Plugin**

# Push the DeepFashion data set in:
```bash
<PLUGIN ROOT>/files/
```


# From development root

```bash
bin/console plugin:install --activate DeepFashion
```

# Run initialize demo data command:

```php

php -d memory_limit=-1  bin/console deepfashion:demodata

```
