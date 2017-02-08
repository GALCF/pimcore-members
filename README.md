# Pimcore Members
Add user authentication and document restriction to pimcore 4.0.

#### Requirements
* Pimcore 4.3

#### Features
* Create Members in Backend
* Allow Members to register in frontend
* Restrict Documents to specific User Roles

### Installation
Some installation advices. 

**Handcrafted Installation**   
1. Download Plugin  
2. Rename it to `Members`  
3. Place it in your plugin directory  
4. Activate & install it through backend 

**Composer Installation**  
1. Add code below to your `composer.json`    
2. Activate & install it through backend

```json
"require" : {
    "dachcom-digital/members" : "1.3.0",
}
```

**Override Templates**

To override the Members scripts, just create a members folder in your scripts folder to override templates:
 
 `/website/views/scripts/members/profile/default.php`
 
### Documents

**Hide restricted pages in navigation**

Pimcore does not allow to manipulate the navigation globally without caching it.
Members provides some helpers to fix that. Use your *pimcoreNavigation* like that:

```php
<?= $this->pimcoreNavigation(

    $this->document,
    $mainNavStartNode,
    null,
    function ($page, $document) {
        \Members\Tool\Observer::bindRestrictionToNavigation($document, $page);
    },
    \Members\Tool\Observer::generateNavCacheKey()
); ?>
```

### Assets

Pure Asset restriction is not supported right now. However, Members will install a protected folder
called "restricted-assets". all assets placed in this folder are protected from frontend calls.

**Get Asset Data**

To get the asset data you need to take some simple steps:
 
1. create an object class called "download"
3. set the object parent class (see section *"Objects"* below) 
3. add a file field called *"file"* and text field called  *"title"*
4. create a download object, add a asset to the file field
5. add restriction to object (*"access privileges"* tab)
6. call the UrlServant Helper, see example below.

*Example*

```html
<a href="<?= \Members\Tool\UrlServant::generateAssetUrl($download->getFile(), $download->getId()); ?>">
    <?= $download->getTitle(); ?>
</a>
```

If you want to get multiple downloads at once, you can use the `generateAssetPackageUrl()` method.
This will create a zip file on the fly, so no temp files on your server!

*Example*

```php
<?php

    $packageData = array(
        array('asset' => $download1->getFile(), 'objectProxyId' => $download1->getId()),
        array('asset' => $download2->getFile(), 'objectProxyId' => $download2->getId())
    );
    
?>

<a href="<?= \Members\Tool\UrlServant::generateAssetPackageUrl( $packageData ); ?>">Download Zip</a>

```

### Objects

#### Register
To add the "restriction" tab in backend, you need to register it first. Edit the `members_configurations.php` first:

```php
<?php

//search for key "core.settings.object.allowed" and add your objects (name)
"key"  => "core.settings.object.allowed",
"data" => [ "News" ]

?>
```

#### Add Listener
Just extend your object classes with `\Members\Model\Object`. Now you're able to check the restriction of your object:

```php
<?php
$list = new Object\YourObject\Listing();
$objects = $list->getObjects();

foreach($objects as $object)
{
    echo $object->getRestricted(); //bool true|false
}
?>
```

#### Restrict objects in view (static route restrictions)

In some cases, objects are bounded to the view. For example a news, blog or a product object. In that case you probably added a static route (www.site.com/news/your-news).
Even if the object has a restriction, the view will not notice it and the user would be able to open the view. Because Members cannot detect the related Object based on a static route, you need to take care about that.
Returning the object will be enough, Members takes care of everything else.

**Example** (add this to your `startup.php`)

```php
<?php
\Pimcore::getEventManager()->attach(

    'members.restriction.object', function(\Zend_EventManager_Event $e)
    {
        $params = $e->getParam('params');
        if( $params['controller'] === 'news')
        {
            $newsId = $params['newsId'];
            $object = \Pimcore\Model\Object\News::getById( $newsId );
            return $object;
        }

        return FALSE;
    }
    
);
?>
```

### Event API
For more information about using Event API please check [pimcore documentation](https://www.pimcore.org/wiki/pages/viewpage.action?pageId=16854309).

**Example**

```php
<?php
\Pimcore::getEventManager()->attach(
    'members.register.post', array('\Website\Events\Members\Auth', 'postRegister'), 10
);
?>
```
        
**`members.register.validate`**  
Allows to override validation of register form data. Your callback must return configured instance of `\Zend_Filter_Input`. See `\Members\Events\Register::validate()` for default implementation.

**`members.update.validate`**  
Allows to override validation of update form data. Your callback must return configured instance of `\Zend_Filter_Input`. See `\Members\Events\Register::validate()` for default implementation.

**`members.register.post`**  
Allows to define what should be done after member object was created. By default member object is unpublished - which means that account is inactive and members has to confirm it via mail. There is also a `activate` callback implemented which enables the account immediately after registering. If you set `actions.postRegister` to `FALSE` in `config/members-configurations.php` members must be activated by admin.
    
**`members.update.post`**  
Allows to define what should be done after member object has been updated.

**`members.confirm.post`**  
Allows to define what should be done after member object has been published.
    
**`members.password.reset`**  
Allows to override validation of password reset form data. Your callback must return configured instance of `\Zend_Filter_Input`. See `\Members\Events\Password::reset()` for default implementation.

**`members.password.change`**  
Allows to override validation of password change form data. Your callback must return configured instance of `\Zend_Filter_Input`. See `\Members\Events\Password::change()` for default implementation.
        
**`members.restriction.object`**  
Validate view restricted objects (see section Objects)

## Copyright and license
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.ch)  
For licensing details please visit [LICENSE.md](LICENSE.md)  