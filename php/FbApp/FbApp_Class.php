<?php
/*
 * $Id: FbApp_Class.php 53 2011-07-13 16:28:46Z genghishack $
 * 
 * FbApp_Class.php - provides basic functionality for a facebook application
 * 
 * Must be extended so that specific properties can be set for the application
 * 
 */
abstract class FbApp_Class
{
    // These properties must be defined by the extending class

    protected $app_id;
    protected $app_secret;
    protected $app_uri;
    protected $app_title;
    protected $app_type;
    protected $app_image;
    protected $app_site_name;
    protected $app_admins;
    protected $app_description;
    
    protected $db_name;
    protected $db_table_user;

    
    // These properties do not need to be defined by the extending class
    
    protected $user_record;
    protected $user_perms;

    protected $access_token;
    

    public function __construct($params=array())
    {
        global $FB, $Page, $Site;
        
        $this->FB = $FB;
        $this->Page = $Page;
        $this->Site = $Site;
        
        // App must have these vars set in order to function
        if (empty($this->app_id)) {
            error_log('Error: Facebook App ID has not been set');
        }
        if (empty($this->app_secret)) {
            error_log('Error: Facebook App Secret has not been set');
        }
        if (empty($this->app_uri)) {
            error_log('Error: Facebook App URI has not been set');
        }
        
        $this->registerFacebookSDK();
        
        if (!$Site->isModule)
        {
            $this->registerMetaTags();
        }
        
        // Get the application access token from Facebook.
        $sAppAccessToken = $this->requestAppAccessToken();
        if ($sAppAccessToken) {
            $this->setAppAccessToken($sAppAccessToken);
        };
        
        // Check the 'signed_request' parameter for a user_id.  If one exists, remove it
        // from the DB.  This means that a user has de-authorized the application.
        $rSignedRequest = $this->FB['sdk']->getSignedRequest();
        if (isset($rSignedRequest['user_id']))
        {
            $sUserIdToRemove = $rSignedRequest['user_id'];
            $this->deleteUserRecord($sUserIdToRemove);
        }
    
        // If there's a user, it means they're logged into our app.  Get whatever data
        // we have for them from our DB, or create a record.
        if ($this->FB['user']) 
        {
            // Get the user record from our DB, if one exists.
            $this->user_record = $this->retrieveUserRecord();
            
            // If we don't have a record for this user, create one.
            if (null == $this->user_record)
            {
                $this->handleUserCreation();
            }
      
            // Get the permissions that have been granted to our app by this user from facebook.
            $this->user_perms = $this->queryUserPermissions();
        
            if ($this->user_perms['offline_access'] == 1)
            {
                // TODO: this function should return the user record so we don't have to query it again afterward.
                $this->storeUserAccessToken();
                $this->user_record = $this->retrieveUserRecord();
            }
      
            // testing posting to the user's page through facebook api - this works
            // $FB['sdk']->api('/me/feed', 'post', array('message' => 'this is a test'));
            
            // test posts to likers' pages through facebook api - this also works
            // $FB['sdk']->api('/feed', 'post', array('message' => "testing a post to likers' feeds"));
        }
    
    }
  
    protected function registerFacebookSDK()
    {
        $this->FB['sdk'] = new Facebook(array(
             'appId'  => $this->app_id
            ,'secret' => $this->app_secret
            ,'cookie' => true
        ));
        
        $this->FB['session'] = $this->FB['sdk']->getSession();
        $fb_session_json = json_encode($this->FB['session']);
        
        $this->FB['user'] = false;
        
        if ($this->FB['session']) {
            try {
                $this->FB['user'] = $this->FB['sdk']->api('/me');
            } catch (FacebookApiException $e) {
                error_log($e);
            }
        }
    
        if (!$this->Site->isModule)
        {
            $this->Page->registerJsTrailing("
        window.fbAsyncInit = function() {
          FB.init({
            appId   : '{$this->FB['sdk']->getAppId()}',
            session : {$fb_session_json}, // don't refetch the session when PHP already has it
            status  : true, // check login status
            cookie  : true, // enable cookies to allow the server to access the session
            xfbml   : true // parse XFBML
          });
  
          // whenever the user logs in, we refresh the page
          FB.Event.subscribe('auth.login', function(response) {
              // What to do here:
              // Rather than refreshing the page, we need to do an asynchronous call to a script that will create the user entry in the DB.
              
              // response.status = connected, notConnected or unknown
              // response.session = session object
              // response.perms = comma separated permissions string
              
              // console.info(response);
              
              // var uid = response.session.uid;
              
              // call a script that creates the user record and returns a status
              $.ajax({
                   url:'http://fb-ratewatcher.intermundonet.com/createUser.php'
                  ,success: function(data) {
                      if (data.success)
                      {
                          $('.fb_controls_right .signup').hide();
                          header.postSignupToWall();
                      }
                   }
                  ,dataType: 'json'
              });
              
              //self.location.href = 'http://fb-ratewatcher.intermundonet.com/';
          });

          // Attempt to resize the iFrame whenever the content changes size
          FB.Canvas.setAutoResize();
        };
  
        // TODO: Not sure I like this way that FB loads the JS SDK asynchronously.  May want to re-write.
        (function() {
          var e = document.createElement('script');
          e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
          e.async = true;
          document.getElementById('fb-root').appendChild(e);
        }());
            ");
        } 
    }
  
    protected function handleUserCreation()
    {
        // TODO: this function should return the user record so we don't have to query it again afterward.
        $this->storeUserRecord();
        $this->user_record = $this->retrieveUserRecord();
    }

    protected function deleteUserRecord($fb_user_id)
    {
        IMMDB::runQuery(
            $this->db_name,
            "DELETE FROM `{$this->db_table_user}` WHERE `fb_user_id` = '{$fb_user_id}'"
        );
    } 
    
    protected function retrieveUserRecord()
    {
        return IMMDB::getRow(
            $this->db_name,
            $this->db_table_user,
            'fb_user_id',
            $this->FB['sdk']->getUser()
        );
    }
  
    protected function storeUserRecord()
    {
        $sInsertUserQuery = "INSERT INTO `{$this->db_table_user}` 
                                         (`fb_user_id`) 
                                  VALUES ({$this->FB['sdk']->getUser()})";
                                
        IMMDB::runQuery($this->db_name, $sInsertUserQuery);
        
        // TODO: return the resulting record
    }
  
    protected function storeUserAccessToken()
    {
        // This is only necessary when we have been granted a persistent access token by the user.
        // i.e. 'offline_access' permission, which is an access token with 0 expiration.
    
        $sUpdateUserQuery = "UPDATE `{$this->db_table_user}`
                                SET `access_token` = '{$this->FB['session']['access_token']}'
                              WHERE `fb_user_id` = '{$this->FB['sdk']->getUser()}'";
                          
        IMMDB::runQuery($this->db_name, $sUpdateUserQuery);
    
        // TODO: error handling, result codes
    }
  
    protected function requestAppAccessToken()
    {
        $ch = curl_init('https://graph.facebook.com/oauth/access_token');
        
        // TODO: this is insecure... I don't know if it matters but we
        // may want to address
        
        // can also use type = client_cred
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
             'grant_type'    => 'client_credentials'
            ,'client_id'     => $this->FB['sdk']->getAppId()
            ,'client_secret' => $this->FB['sdk']->getApiSecret()
        ));
    
        $sResponse = str_replace('access_token=', '', curl_exec($ch));
        curl_close($ch);
    
        return $sResponse;
    }
  
    protected function queryUserPermissions()
    {
        $rPerms = $this->FB['sdk']->api(array(
       'query'  => "SELECT publish_stream, 
                           create_event, 
                           rsvp_event, 
                           sms, 
                           offline_access,
                           publish_checkins,
                           user_about_me,
                           user_activities,
                           user_birthday,
                           user_education_history,
                           user_events,
                           user_groups,
                           user_hometown,
                           user_interests,
                           user_likes,
                           user_location,
                           user_notes,
                           user_online_presence,
                           user_photo_video_tags,
                           user_photos,
                           user_relationships,
                           user_relationship_details,
                           user_religion_politics,
                           user_status,
                           user_videos,
                           user_website,
                           user_work_history,
                           user_checkins,
                           friends_about_me,
                           friends_activities,
                           friends_birthday,
                           friends_education_history,
                           friends_events,
                           friends_groups,
                           friends_hometown,
                           friends_interests,
                           friends_likes,
                           friends_location,
                           friends_notes,
                           friends_online_presence,
                           friends_photo_video_tags,
                           friends_photos,
                           friends_relationships,
                           friends_relationship_details,
                           friends_religion_politics,
                           friends_status,
                           friends_videos,
                           friends_website,
                           friends_work_history,
                           friends_checkins,
                           email,
                           read_friendlists,
                           read_insights,
                           read_mailbox,
                           read_requests,
                           read_stream,
                           xmpp_login,
                           ads_management,
                           manage_pages
                      FROM permissions 
                     WHERE uid={$this->FB['sdk']->getUser()}"
      ,'method' => 'fql.query'
        ));
    
        return ($rPerms[0]); 
    }
  
    protected function registerMetaTags()
    {
        $this->Page->registerMetaTag(array( // will appear in 'so-and-so likes [TITLE] on [SITE_NAME]', also as application name
            'property' => 'og:title',
            'content'  => $this->app_title
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'og:type',
            'content'  => $this->app_type
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'og:url',
            'content'  => $this->app_uri
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'og:image',
            'content'  => $this->app_image
        ));
        $this->Page->registerMetaTag(array( // will appear in 'so-and-so likes [TITLE] on [SITE_NAME]'
            'property' => 'og:site_name', 
            'content'  => $this->app_site_name
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'fb:admins',
            'content'  => $this->app_admins
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'fb:app_id',
            'content'  => $this->app_id
        ));
        $this->Page->registerMetaTag(array(
            'property' => 'og:description',
            'content'  => $this->app_description
        ));
    }

    // Simple getters and setters
    
    protected function setId($value)
    {
        $this->app_id = $value;
    }
    
    public function getId()
    {
        return $this->app_id;
    }
    
    protected function setSecret($value)
    {
        $this->app_secret = $value;
    }
    
    public function getSecret()
    {
        return $this->app_secret;
    }
    
    protected function setUri($value)
    {
        $this->app_uri = $value;
    }
    
    public function getUri()
    {
        return $this->app_uri;
    }
  
    protected function setTitle($value)
    {
        $this->app_title = $value;
    }
    
    public function getTitle()
    {
        return $this->app_title;
    }
    
    protected function setType($value)
    {
        $this->app_type = $value;
    }
    
    public function getType()
    {
        return $this->app_type;
    }
    
    protected function setImage($value)
    {
        $this->app_image = $value;
    }
    
    public function getImage()
    {
        return $this->app_image;
    }
    
    protected function setSiteName($value)
    {
        $this->app_site_name = $value;
    }
    
    public function getSiteName()
    {
        return $this->app_site_name;
    }
    
    protected function setAdmins($value)
    {
        $this->app_admins = $value;
    }
    
    public function getAdmins()
    {
        return $this->app_admins;
    }
    
    protected function setDescription($value)
    {
        $this->app_description = $value;
    }
    
    public function getDescription()
    {
        return $this->app_description;
    }
    
    protected function setDbName($value)
    {
        $this->db_name = $value;
    }
    
    public function getDbName()
    {
        return $this->db_name;
    }
    
    protected function setDbUserTable($value)
    {
        $this->db_table_user = $value;
    }
    
    public function getDbUserTable()
    {
        return $this->db_table_user;
    }
    
    public function setAppAccessToken($value)
    {
        $this->access_token = $value;
    }
    
    public function getAppAccessToken()
    {
        return $this->access_token;
    }
}
?>