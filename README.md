FollowCheckr
============

Follow Checkr is a follow checker written in PHP that stores no data locally to minimize site load and was written as a proof of concept.

[nquinlan's PHP OAuth library](https://github.com/nquinlan/Tumblr-OAuth) is used to handle OAuth and make requests to the tumbler API.

The session storing code is also provided by nquinlan's demo. The relevant code logic occurs in check.php and index.html demonstrates the capability of FollowCheckr.

Most of the processing done in FollowCheckr is done in [FollowCheckr.php](https://github.com/jjestrel/FollowCheckr/blob/master/src/FollowCheckr.php)
