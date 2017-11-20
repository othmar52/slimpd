/* Copyright (C) 2016 Evan Sonderegger <esonderegger@users.noreply.github.com>
 *               2017 othmar52 <othmar52@users.noreply.github.com>
 *
 * This file is part of sliMpd - a php based mpd web client
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Many thanks to Evan Sonderegger who created this rock solid piece of javascript
 * https://github.com/esonderegger/web-audio-peak-meter
 * 
 * Evan's version had been slightly modified to allow unlimitid amount of WebAudioPeakMeter instances
 */
function WebAudioPeakMeter() {
    this.options = {
        borderSize: 2,
        fontSize: 0,
        backgroundColor: 'black',
        tickColor: '#ddd',
        gradient: [
            'red 1%',
            '#ff0 16%',
            'lime 45%',
            '#080 100%'
        ],
        dbRange: 48,
        dbTickSize: 6,
        maskTransition: '0.1s',
    };
    this.tickWidth;
    this.elementWidth;
    this.elementHeight;
    this.meterHeight;
    this.meterWidth;

    this.meterTop;
    this.vertical = true;
    this.channelCount = 1;
    this.channelMasks = [];
    this.channelPeaks = [];
    this.channelPeakLabels = [];
    this.maskSizes = [];
    this.textLabels = [];

    this.meterElement = null;
    this.elementWidth = 0;
    this.elementHeight = 0;
}

WebAudioPeakMeter.prototype.getBaseLog = function(x, y) {
    return Math.log(y) / Math.log(x);
};

WebAudioPeakMeter.prototype.dbFromFloat = function(floatVal) {
    return this.getBaseLog(10, floatVal) * 20;
};

WebAudioPeakMeter.prototype.setOptions = function(userOptions) {
    for (var k in userOptions) {
        if(userOptions.hasOwnProperty(k)) {
            this.options[k] = userOptions[k];
        }
    }
    this.tickWidth = this.options.fontSize * 2.0;
    this.meterTop = this.options.fontSize * 1.5 + this.options.borderSize;
};

WebAudioPeakMeter.prototype.createMeterNode = function(sourceNode, audioCtx) {
    var meterNode = audioCtx.createScriptProcessor(2048, sourceNode.channelCount, sourceNode.channelCount);
    sourceNode.connect(meterNode);
    meterNode.connect(audioCtx.destination);
    return meterNode;
};

WebAudioPeakMeter.prototype.createContainerDiv = function(parent) {
    this.meterElement = document.createElement('div');
    this.meterElement.style.position = 'relative';
    this.meterElement.style.width = this.elementWidth + 'px';
    this.meterElement.style.height = this.elementHeight + 'px';
    this.meterElement.style.backgroundColor = this.options.backgroundColor;
    parent.appendChild(this.meterElement);
    return this.meterElement;
};

WebAudioPeakMeter.prototype.createMeter = function(domElement, meterNode, optionsOverrides) {
    this.setOptions(optionsOverrides);
    this.elementWidth = domElement.clientWidth;
    this.elementHeight = domElement.clientHeight;
    this.createContainerDiv(domElement);
    if (this.elementWidth > this.elementHeight) {
        this.vertical = false;
    }
    this.meterHeight = this.elementHeight - this.meterTop - this.options.borderSize;
    this.meterWidth = this.elementWidth - this.tickWidth - this.options.borderSize;
    this.createTicks();
    this.createRainbow();
    this.channelCount = meterNode.channelCount;
    var channelWidth = this.meterWidth / this.channelCount;
    if (!this.vertical) {
        this.channelWidth = this.meterHeight / this.channelCount;
    }
    var channelLeft = this.tickWidth;
    if (!this.vertical) {
        channelLeft = this.meterTop;
    }
    for (var i = 0; i < this.channelCount; i++) {
        this.createChannelMask(
            this.options.borderSize,
            channelLeft,
            false
        );
        this.channelMasks[i] = this.createChannelMask(
            channelWidth,
            channelLeft,
            this.options.maskTransition
        );
        this.channelPeaks[i] = 0.0;
        this.channelPeakLabels[i] = this.createPeakLabel(channelWidth, channelLeft);
        channelLeft += channelWidth;
        this.maskSizes[i] = 0;
        this.textLabels[i] = '-∞';
    }
    meterNode.onaudioprocess = this.updateMeter.bind(this);
    this.meterElement.addEventListener('click', function() {
        for (var i = 0; i < this.channelCount; i++) {
            this.channelPeaks[i] = 0.0;
            this.textLabels[i] = '-∞';
        }
    }, false);
    this.paintMeter();
};

WebAudioPeakMeter.prototype.createTicks = function() {
    var numTicks = Math.floor(this.options.dbRange / this.options.dbTickSize);
    var dbTickLabel = 0;
    if (this.vertical) {
        var dbTickTop = this.options.fontSize + this.options.borderSize;
        for (var i = 0; i < numTicks; i++) {
            var dbTick = document.createElement('div');
            this.meterElement.appendChild(dbTick);
            dbTick.style.width = this.tickWidth + 'px';
            dbTick.style.textAlign = 'right';
            dbTick.style.color = this.options.tickColor;
            dbTick.style.fontSize = this.options.fontSize + 'px';
            dbTick.style.position = 'absolute';
            dbTick.style.top = dbTickTop + 'px';
            dbTick.textContent = dbTickLabel + '';
            dbTickLabel -= this.options.dbTickSize;
            dbTickTop += this.meterHeight / numTicks;
        }
    } else {
        this.tickWidth = this.meterWidth / numTicks;
        var dbTickRight = this.options.fontSize * 2;
        for (var i = 0; i < numTicks; i++) {
            var dbTick = document.createElement('div');
            this.meterElement.appendChild(dbTick);
            dbTick.style.width = this.tickWidth + 'px';
            dbTick.style.textAlign = 'right';
            dbTick.style.color = this.options.tickColor;
            dbTick.style.fontSize = this.options.fontSize + 'px';
            dbTick.style.position = 'absolute';
            dbTick.style.right = dbTickRight + 'px';
            dbTick.textContent = dbTickLabel + '';
            dbTickLabel -= this.options.dbTickSize;
            dbTickRight += this.tickWidth;
        }
    }
};

WebAudioPeakMeter.prototype.createRainbow = function() {
    var rainbow = document.createElement('div');
    this.meterElement.appendChild(rainbow);
    rainbow.style.width = this.meterWidth + 'px';
    rainbow.style.height = this.meterHeight + 'px';
    rainbow.style.position = 'absolute';
    rainbow.style.top = this.meterTop + 'px';
    if (this.vertical) {
        rainbow.style.left = this.tickWidth + 'px';
        var gradientStyle = 'linear-gradient(to bottom, ' +
            this.options.gradient.join(', ') + ')';
    } else {
        rainbow.style.left = this.options.borderSize + 'px';
        var gradientStyle = 'linear-gradient(to left, ' +
            this.options.gradient.join(', ') + ')';
    }
    rainbow.style.backgroundImage = gradientStyle;
    return rainbow;
};

WebAudioPeakMeter.prototype.createPeakLabel = function(width, left) {
    var label = document.createElement('div');
    this.meterElement.appendChild(label);
    label.style.textAlign = 'center';
    label.style.color = this.options.tickColor;
    label.style.fontSize = this.options.fontSize + 'px';
    label.style.position = 'absolute';
    label.textContent = '-∞';
    if (this.vertical) {
        label.style.width = width + 'px';
        label.style.top = this.options.borderSize + 'px';
        label.style.left = left + 'px';
    } else {
        label.style.width = this.options.fontSize * 2 + 'px';
        label.style.right = this.options.borderSize + 'px';
        label.style.top = (width * 0.25) + left + 'px';
    }
    return label;
};

WebAudioPeakMeter.prototype.createChannelMask = function( width, left, transition) {
    var channelMask = document.createElement('div');
    this.meterElement.appendChild(channelMask);
    channelMask.style.position = 'absolute';
    if (this.vertical) {
        channelMask.style.width = width + 'px';
        channelMask.style.height = this.meterHeight + 'px';
        channelMask.style.top = this.meterTop + 'px';
        channelMask.style.left = left + 'px';
    } else {
        channelMask.style.width = this.meterWidth + 'px';
        channelMask.style.height = width + 'px';
        channelMask.style.top = left + 'px';
        channelMask.style.right = this.options.fontSize * 2 + 'px';
    }
    channelMask.style.backgroundColor = this.options.backgroundColor;
    if (transition) {
        if (this.vertical) {
            channelMask.style.transition = 'height ' + this.options.maskTransition;
        } else {
            channelMask.style.transition = 'width ' + this.options.maskTransition;
        }
    }
    return channelMask;
};

WebAudioPeakMeter.prototype.maskSize = function(floatVal) {
    var meterDimension = this.vertical ? this.meterHeight : this.meterWidth;
    if (floatVal === 0.0) {
        return meterDimension;
    } else {
        var d = this.options.dbRange * -1;
        var returnVal = Math.floor(this.dbFromFloat(floatVal) * meterDimension / d);
        if (returnVal > meterDimension) {
            return meterDimension;
        } else {
            return returnVal;
        }
    }
};

WebAudioPeakMeter.prototype.updateMeter = function(audioProcessingEvent) {
    var inputBuffer = audioProcessingEvent.inputBuffer;
    var i;
    var channelData = [];
    var channelMaxes = [];
    for (i = 0; i < this.channelCount; i++) {
        channelData[i] = inputBuffer.getChannelData(i);
        channelMaxes[i] = 0.0;
    }
    for (var sample = 0; sample < inputBuffer.length; sample++) {
        for (i = 0; i < this.channelCount; i++) {
            if (Math.abs(channelData[i][sample]) > channelMaxes[i]) {
                channelMaxes[i] = Math.abs(channelData[i][sample]);
            }
        }
    }
    for (i = 0; i < this.channelCount; i++) {
        this.maskSizes[i] = this.maskSize(channelMaxes[i], this.meterHeight);
        if (channelMaxes[i] > this.channelPeaks[i]) {
            this.channelPeaks[i] = channelMaxes[i];
            this.textLabels[i] = this.dbFromFloat(this.channelPeaks[i]).toFixed(1);
        }
    }
};

WebAudioPeakMeter.prototype.paintMeter = function() {
    for (var i = 0; i < this.channelCount; i++) {
        if (this.vertical) {
            this.channelMasks[i].style.height = this.maskSizes[i] + 'px';
        } else {
            this.channelMasks[i].style.width = this.maskSizes[i] + 'px';
        }
        this.channelPeakLabels[i].textContent = this.textLabels[i];
    }
    window.requestAnimationFrame(this.paintMeter.bind(this));
};
