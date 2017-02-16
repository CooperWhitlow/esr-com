// start Jquery magic
$( document ).ready(function() {
	// update links in navagation from page links to ID links within the same document
	$( "#menu-item-115 > a" ).attr("href", "http://www.emeraldscreenrepair.com/#how-it-works");

	$( "#menu-item-115 > a" ).click(function() {
    	$('html, body').animate({
    	    scrollTop: $("#how-it-works").offset().top
    	}, 1000);
	});
});