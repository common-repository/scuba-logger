jQuery(document).ready(function(){

var rows = jQuery('table.divelist tr.details');
var showlinks = jQuery('.showlink');

jQuery(".showlink").click(function(event){
  var thenum = event.target.id.replace( /^\D+/g, '');
  var rowid = "#visrow_" + thenum;
  jQuery(rowid).toggle();
  var linkid = "#" + event.target.id;
  var text = jQuery(linkid).text();
  if (text == "show") {
  	jQuery(linkid).text("hide");
  } else {
  	jQuery(linkid).text("show");
  }
});

jQuery("#showall").click(function() {
	rows.show();
	showlinks.text("hide");
});

jQuery("#hideall").click(function() {
	rows.hide();
	showlinks.text("show");
});

});
