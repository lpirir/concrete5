ImageEditor.prototype.filter = {};
ImageEditor.prototype.filter.grayscale = Kinetic.Filters.Grayscale;
ImageEditor.prototype.filter.sepia = function (imageData) {
  var i;
  var data = imageData.data;
  for (i = 0; i < data.length; i += 4) {
    data[i]     = (data[i] * 0.393 + data[i + 1] * 0.769 + data[i + 2] * 0.189);
    data[i + 1] = (data[i] * 0.349 + data[i + 1] * 0.686 + data[i + 2] * 0.168);
    data[i + 2] = (data[i] * 0.272 + data[i + 1] * 0.534 + data[i + 2] * 0.131);
  }
};
ImageEditor.prototype.filter.brightness = function (imageData,ob) {
	var adjustment = ob.level;
	var d = imageData.data;
	for (var i=0; i<d.length; i+=4) {
		d[i] += adjustment;
		d[i+1] += adjustment;
		d[i+2] += adjustment;
	}
};
ImageEditor.prototype.filter.restore = function (imageData,ob) {
	var adjustment = ob.level;
  	var d = imageData.data;
  	var g = ob.imageData.data;
	for (var i=0; i<d.length; i+=4) {
		d[i] = g[i];
		d[i+1] = g[i+1];
		d[i+2] = g[i+2];
	}
};