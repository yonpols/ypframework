# YPFramework #

YPFramework is a web application framework inspired by Ruby on Rails and written
in PHP 5.3. Its principles are convention over configuration. It's currently being
developed and tested by one person: Juan Pablo Marzetti. I use it with my work projects
and as a mean to learn about web frameworks development.

## Help ##

Everyone interested in helping on the development and tests is welcome. Also everyone
who wants to help with the documentation is also welcome. This software is released
as free software.

## Installing ##

Currently you can use a tool I've developed called [YPInfrastructure](https://github.com/yonpols/ypfinfrastructure)
to install it or you can simply clone this repository or download it.

If you install YPInfrastructure then go to your shell and type the following

`ypi update`
`ypi install framework ypf`

## Getting started ##

To start development of an application simply run

`ypf new application-name`

A directory will be created under your working path with an skeleton for a new
application. Publish that path on your web servers root and navigate to it in
your browser.

## Documentation ##

Currently there is little docs. One good start point is each important
element in the framework:

* controllers/home_controller.php
* models/sample.php
* views/_layouts/main.html
* views/home/index.hmtl
