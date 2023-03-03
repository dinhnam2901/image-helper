# image-helper
Single class PHP for image helper.  
Main feature:
- Resize the image.
- Getting information of image.

## Basic usage
If you have a image at 'upload/image1.jpg' with: width=1000px, height=1200px.  
And you want to resize it to 'thumb/image1.jpg' with width=300px, and remain ratio as the same (calc height automatically).  
You can use bellow code: 
```
$imageHelper = new ImageHelper();
$imageHelper->resize('upload/image1.jpg', 'thumb/image1', [
    ImageHelper::OPTION_WIDTH => 300
]);
```

## Required
This image helper use GD library. So, ensure you was install and turn on the GD library on your web server.
