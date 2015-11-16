$(document).ready(function() {
	
	//高さ揃え
	$("img").each(function(){
		var hig = $(this).height();
		if(hig > 60){
			$(this).css("margin-top", "-" + ((hig - 80) /2) + "px");
		}
	});
	
	$('.thumbnail').equalHeight();
	
	//サイト絞込み
	$("#search-site").change(function(){
		var site_id	= $(this).val();
		if(site_id == ""){
			$("div.row > div").show();
		} else { 
			$("div.row > div").each(function(){
				if(site_id == $(this).attr("data-siteid")){
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		}
	
	});
});
