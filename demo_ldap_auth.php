<?php
// File Name: demo_ldap_auth.php
// Purpose:   Demonstrate LDAP authorising capabilities in PHP
// Created:   23-Oct-2013 from denis_test_ldap.php of 10-May-2013
// Author:    DS Brown
// Last Edit: 23-Oct-2013 DSB original code
//            24-Oct-2013 DSB added group membership display
//            25-Oct-2013 DSB added test for existence in a group
//            01-Nov-2013 DSB some tidying up prior to submitting to github

$debug = false;  // Set this to true to enable debugging messages

// basic sequence with LDAP is connect, bind, search, interpret search result, close connection

// Some variables defined to avoid hunting in the code...
//
$ldap_dn = "ou=People,dc=uniwa,dc=uwa,dc=edu,dc=au";
$ldap_svr = "ldap.uniwa.uwa.edu.au";
$ldap_domain = "uniwa.uwa.edu.au";

// Get user details from console rather than hard-code into this example.   Run this from console
// as     php demo_ldap_auth.php
//
print "User ID number: ";
$myId=read_stdin();
print "Password: ";
$myPassword = read_stdin();
print "Name of group for which membership confirmation is needed: ";
$myGroup = read_stdin();
print "\n";

// First task, open a connection to the LDAP server
//
if ($debug) print "Connecting to LDAP server ...\n";
$conn=ldap_connect($ldap_svr) or die("Cannot connect to LDAP server!");  // later, will use ldaps

// useful options to perform efficient LDAP searches
//
ldap_set_option ($conn, LDAP_OPT_REFERRALS, 0);
ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);

// Do the bind operation and if successful, perform search
//
if ($conn) { 

    if ($debug) print "Binding for user ID $myId...\n"; 
    if (ldap_bind($conn,"$myId@$ldap_domain","$myPassword")) {

       // ldap query modifiers for when we use ldap_read
       //
       // the attributes to extract, more efficient than reading all of them
       // the ldap_read command requires a filter
       //
       $justthese = array("givenname","sn","memberof","primarygroupid");
       $filter="(objectclass=*)";

       // Start searching...
       //
       if ($debug) print "Searching for (cn=$myId) ...\n";
       $result=ldap_search($conn, $ldap_dn, "cn=$myId", $justthese) or die("No search data found.");  

       if ($debug) print "Number of entries returned is " . ldap_count_entries($conn, $result) . "\n";
    
          // Fill out an "info" array with relevant results...
         //
         if ($debug) print "Getting entries ...\n";
         $info = ldap_get_entries($conn, $result);
         if ($debug) print "Data for " . $info["count"] . " items returned:\n";

         $famname = $info[0]['sn'][0];
         $ownname = $info[0]['givenname'][0];

         if ($info["count"] <> 0) {

            // Get groups and primary group token
            //
            $output = $info[0]['memberof'];
            $token = $info[0]['primarygroupid'][0];

            // Remove first entry - not needed
            //
            array_shift($output);

            // We need to look up the primary group, get list of all groups
            //
            $results2 = ldap_search($conn,$ldap_dn,"(objectcategory=group)",array("distinguishedname","primarygrouptoken"));
            $entries2 = ldap_get_entries($conn, $results2);
    
            // Remove unwanted first entry
            //
            array_shift($entries2);
    
            // Loop through and find group with a matching primary group token
            //
            foreach($entries2 as $e) {
               if($e['primarygrouptoken'][0] == $token) {
                  // Primary group found, add it to output array
                  $output[] = $e['distinguishedname'][0];
                  // Break loop
                  break;
                  }
            }

            print "User $ownname $famname is a member in the following group(s):\n";

            $arrlen = count($output);
            for ($i=0; $i<$arrlen; $i++)
               {
               print $output[$i] . "\n";
               }
            print "\n";
 
            // Is this person a member of the requested group?
            //
            $arrlen = count($output);
            for ($i=0; $i<$arrlen; $i++)
               {    
               $posi = strpos(strtoupper($output[$i]), strtoupper($myGroup));
               if ($posi === false)
                  {
                  print "The string $myGroup was not found\n";
                  }
               else
                  {
                  print "The group $myGroup was found in $output[$i]\n";
                  }
               }

            }
          else {
             print "Could not authenticate this user ID";
             }

          print "Closing connection...\n";
          ldap_close($conn);
          }

       } else {
           print "Unable to connect to LDAP server\n\n";
           }



// Function to read from the command line
function read_stdin()
{
   $fr=fopen("php://stdin","r");   // open pointer to read from stdin
   $input = fgets($fr,128);        // read a max of 128 chars
   $input = rtrim($input);         // remove trailing spaces.
   fclose ($fr);                   // close handle
   return $input;                  // return the text entered
}


?>
