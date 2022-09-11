root@saris:/home/saris# du -hsx * | sort -rh | head -10
160M	admission
37M	billing
22M	Classes
8.3M	accommodation
8.3M	academic
5.2M	asset
4.5M	student
3.3M	template
3.3M	secretary
2.6M	sql

root@saris:/home/saris# cd admission/
root@saris:/home/saris/admission# du -hsx * | sort -rh | head -10
139M	sarismuco.sql
11M	sarismuco.tar.gz
1.6M	temp
1.5M	Report
876K	includes
740K	pdf
644K	include
520K	Report.zip
440K	admReport.zip
420K	images
root@saris:/home/saris/admission# 

root@saris:/home/saris/admission# rm sarismuco.*
root@saris:/home/saris/admission# rm *.zip

root@saris:/home/saris# du -hsx * | sort -rh | head -10
37M	billing
22M	Classes
9.8M	admission
8.3M	accommodation
8.3M	academic
5.2M	asset
4.5M	student
3.3M	template
3.3M	secretary
2.6M	sql


[8/15/16, 10:10:13 PM] Ally Shaban: Unataka kurun
[8/15/16, 10:10:16 PM] Ally Shaban: Sijaitest
[8/15/16, 10:10:28 PM] Dr. Juma Lungo: ndio nataka kuirun
[8/15/16, 10:10:44 PM] Ally Shaban: You may add this $this->save($regObj); inside the add_registration function
[8/15/16, 10:10:58 PM] Ally Shaban: Nimesahau
[8/15/16, 10:11:02 PM] Dr. Juma Lungo: ok
[8/15/16, 10:11:23 PM] Ally Shaban: 'name' => 'NAME',
          'regno' => 'REGNO',
          'gender' => 'SEX',
          'location' => 'ADDRESS',
          'sponsor' => 'SPONSOR',
          'programme' => 'PROGRAMME'
          'institution' => 'INSTITUTION'
          'birth_date' => 'DATE OF BIRTH
[8/15/16, 10:11:37 PM] Ally Shaban: On the right are the columns expected on excel sheet
[8/15/16, 10:11:52 PM] Ally Shaban: and the left are the reference to those columns
[8/15/16, 10:12:31 PM] Ally Shaban: Kwa hiyo ili utest,weka column ya INSTITUTION at the sheet,halafu populate na institution name
[8/15/16, 10:12:59 PM] Ally Shaban: protected static $required_cols_by_transaction = array(
        'NE'=>array('name','birth_date','gender','regno')
        );
[8/15/16, 10:13:15 PM] Ally Shaban: Those are the columns that must be found for the import to go ahead
[8/15/16, 10:13:22 PM] Dr. Juma Lungo: ok
[8/15/16, 10:13:37 PM] Dr. Juma Lungo: ikifanya kazi,
[8/15/16, 10:13:49 PM] Dr. Juma Lungo: kesho tunapiga gui
[8/15/16, 10:13:53 PM] Dr. Juma Lungo: tumemaliza
[8/15/16, 10:14:22 PM] Dr. Juma Lungo: i will finish it up :)
[8/15/16, 10:15:09 PM] Ally Shaban: Hehe
