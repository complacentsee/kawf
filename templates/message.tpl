  <table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td align="left"><a name="{MSG_MID}"><font size="+1" color="#000080"><b>{MSG_SUBJECT}</b></font></a></td>
    <td>&nbsp;&nbsp;&nbsp;</td>
    <td align="right" nowrap="nowrap"><font size="-2">
<!-- BEGIN reply -->
[   <a href="/{FORUM_SHORTNAME}/msgs/{MSG_MID}.phtml#post">Reply</a> ]
<!-- END reply -->
<!-- BEGIN owner -->
    [ <a href="/{FORUM_SHORTNAME}/edit.phtml?mid={MSG_MID}&page={PAGE}">Edit</a> ]
<!-- BEGIN delete -->
    [ <a href="/{FORUM_SHORTNAME}/delete.phtml?mid={MSG_MID}&page={PAGE}">Delete</a> ]
<!-- END delete -->
<!-- BEGIN undelete -->
    [ <a href="/{FORUM_SHORTNAME}/undelete.phtml?mid={MSG_MID}&page={PAGE}">Undelete</a> ]
<!-- END undelete -->
<!-- BEGIN statelocked -->
    <b>Status locked</b>
<!-- END statelocked -->
<!-- END owner -->
    </font></td>
  </tr>
  </table>

  <font size="-2">
<!-- BEGIN account_id -->
     User account number (aid): <a href="http://forums.{DOMAIN}/account/{MSG_AID}.phtml">{MSG_AID}</a>
<!-- END account_id -->
<!-- BEGIN forum_admin -->
     <a href="http://forums.{DOMAIN}/admin/su.phtml?aid={MSG_AID}">su</a> ({MSG_EMAIL})
<!-- END forum_admin -->
<!-- BEGIN advertiser -->
     <b>Advertiser</b>
<!-- END advertiser -->
<!-- BEGIN sponsor -->
     <b><a href="http://kawf.sourceforge.net/">Circle of Trust</a></b>
<!-- END sponsor -->
     <br>
<!-- BEGIN message_ip -->
     Posting IP Address: {MSG_IP}<br>
<!-- END message_ip -->
     <b>Posted by {MSG_NAMEEMAIL} on {MSG_DATE}</b>
<!-- BEGIN parent -->
     In Reply to: <a href="{PMSG_MID}.phtml">{PMSG_SUBJECT}</a> posted by {PMSG_NAME} on {PMSG_DATE}<br>
<!-- END parent -->
   </font>
   <p>
{MSG_MESSAGE}
<!-- BEGIN changes -->
     <b>Changes:</b><br>
     <p class="changes">{MSG_CHANGES}</p>
<!-- END changes -->
