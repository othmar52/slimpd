self.pollInterval = 2000;
self.pollUrl = "/mpdstatus";
self.addEventListener("message", function(e) {
	var data = e.data;
	if(typeof self[e.data.cmd] !== "function") {
		return;
	}
	self[e.data.cmd](e.data);
}, false);

self.poll = function(queryString) {
	var ajax = new XMLHttpRequest();
	//console.log("pollworker.pollUrl", self.pollUrl);
	ajax.open("GET", self.pollUrl + ((queryString)?queryString:""), true);
	ajax.onreadystatechange = function(){
		if(this.readyState !== XMLHttpRequest.DONE){
			return;
		}
		if(this.status !== 200){
			self.postMessage("Response status is not 200 but " + this.statusText + "! terminating worker...");
			self.close();
		}
		try {
			self.postMessage(JSON.parse(this.responseText));
			return;
		} catch(e) {
			self.postMessage("Poll-response is not parsable as JSON! terminating worker...");
			self.close();
			return;
		}
	};
	ajax.send(null);
	self.poller = setTimeout(
		self.poll,
		self.pollInterval
	);
};

self.start = function() {
	self.poll();
};

self.setMiliseconds = function(data) {
	if(data.value < 2000) {
		self.postMessage("Invalid value for miliseconds: " + data.value + "! terminating worker...");
		self.close();
		return;
	}
	self.pollInterval = data.value;
};

self.setPollUrl = function(data) {
	self.pollUrl = data.value;
};

self.refreshInterval = function() {
	clearTimeout(self.poller);
	self.poll();
};

self.refreshIntervalDelayed = function() {
	clearTimeout(self.poller);
	setTimeout(function(){self.poll("?force=1");},200);
};

self.stop = function() {
	self.close();
};
