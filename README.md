# moodle-mod_concordance

Module allowing the management of a learning by concordance activity, including the management of the panel of experts and the compilation of answers.

Activité permettant la gestion d'une formation ou évaluation par concordance, incluant la gestion du panel d'experts et la compilation des réponses obtenues.
## Security configuration
You can secure concordance module by creating a system role for panelists.
### Create a system role
moosh commands
```shell
moosh role-create -d "Panelist system role" -n "Panelist" -c system panelist
# this return id of newly created role <roleid> 
moosh role-update-capability -i <roleid> mod/forumng:grade prohibit 1
moosh role-update-capability -i <roleid> moodle/my:manageblocks prohibit 1
moosh role-update-capability -i <roleid> moodle/site:sendmessage prohibit 1
moosh role-update-capability -i <roleid> moodle/site:viewparticipants prohibit 1
moosh role-update-capability -i <roleid> moodle/course:viewparticipants prohibit 1
moosh role-update-capability -i <roleid> moodle/user:changeownpassword prohibit 1
moosh role-update-capability -i <roleid> moodle/user:editownmessageprofile prohibit 1
moosh role-update-capability -i <roleid> moodle/user:editownprofile prohibit 1
moosh role-update-capability -i <roleid> moodle/user:manageownfiles prohibit 1
moosh role-update-capability -i <roleid> moodle/badges:manageownbadges prohibit 1
moosh role-update-capability -i <roleid> moodle/category:viewcourselist prohibit 1
moosh role-update-capability -i <roleid> moodle/course:create prohibit 1
moosh role-update-capability -i <roleid> tool/dataprivacy:requestdelete prohibit 1
moosh role-update-capability -i <roleid> moodle/webservice:createtoken prohibit 1
moosh role-update-capability -i <roleid> moodle/user:editprofile prohibit 1
```
You can change other capabilities depending of your additional Moodle plugin installed
* e.g for module customcert
```shell
moosh role-update-capability -i <roleid> mod/customcert:viewallcertificates prohibit 1
```
## Set the newly created role in Concordance module settings
Site administration -> Plugins -> Activity modules -> Concordance
* set System role for panelist to your newly created panelist role.
* moosh command 
```shell
moosh config-set panelistssystemrole <roleid> mod_concordance
```
## Protect courses category dedicated to panel experts
* this category is the one set in Concordance module settings as "Category for courses for panels of experts"
* change permissions by prohibit moodle/category:viewcourselist to the newly created panelist role.
