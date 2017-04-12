$(document).ready(function () {
    //$('#legend-rep').html($('.highcharts-legend').html());
    //$('#legend-rep').addClass('highcharts-legend');
});

var chart = Highcharts.chart('charts', {
    chart: {
        zoomType: 'x',
        resetZoomButton: {
            position: {
                align: 'left',
                x: 150
            }
        },
        //panning: false,
        pinchType: false
    },
    title: {
        text: ''
    },
    subtitle: {
        text: ''
    },
    xAxis: [{
            type: 'datetime',
            title: {
                text: 'Date'
            }
        }],
    yAxis: [
        {
            max: 20,
            min: 0,
            tickInterval: 1,
            labels: {
                format: '{value}',
                style: {
                    color: '#F9E79F'
                }
            },
            title: {
                text: '5 year rain avg',
                style: {
                    color: '#F9E79F'
                }
            },
            visible: false
        },
        {
            max: 20,
            min: 0,
            tickInterval: 1,
            labels: {
                format: '{value}',
                style: {
                    color: '#F4D03F'
                }
            },
            title: {
                text: '5 year rain avg',
                style: {
                    color: '#F4D03F'
                }
            },
            visible: false
        },
        {
            max: 20,
            min: 0,
            tickInterval: 1,
            labels: {
                format: '{value}',
                style: {
                    color: '#EB984E'
                }
            },
            title: {
                text: 'Current Rain',
                style: {
                    color: '#EB984E'
                }
            },
            visible: false
        },
        {
            max: 30,
            min: 0,
            tickInterval: 1,
            labels: {
                format: '{value}',
                style: {
                    color: Highcharts.getOptions().colors[0]
                }
            },
            title: {
                text: 'Avaliable Water',
                style: {
                    color: Highcharts.getOptions().colors[0]
                }
            },
            plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#ff0000'
                }]
        },
        {
            max: 150,
            min: 20,
            tickInterval: 20,
            labels: {
                format: '{value}',
                style: {
                    color: '#B03A2E'
                }
            },
            title: {
                text: 'Yield Potential',
                style: {
                    color: '#B03A2E'
                }
            },
            plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#ff0000'
                }]
        }
    ],
    tooltip: {
        shared: true,
        padding: 15,
        headerFormat: '<span style="font-size: 15px;">{point.key}</span><br>',
        positioner: function (labelWidth, labelHeight, point) {
            return {x: point.plotX, y: point.plotY - 150};
        }
        //followTouchMove: false

    },
    legend: {
        enabled: false,
        layout: 'vertical',
        align: 'left',
        floating: true,
        x: 150,
        verticalAlign: 'top',
        y: 100,
        backgroundColor: '#FFFFFF',
    },
    scrollbar: {
        enabled: true
    },
    series: [
        {
            name: '10 year rain avg',
            color: '#F9E79F',
            type: 'column',
            yAxis: 0,
            data: data[1],
            tooltip: {
                valueSuffix: ' mm'
            }
        },
        {
            name: '5 year rain avg',
            color: '#F4D03F',
            type: 'column',
            yAxis: 0,
            data: data[0],
            tooltip: {
                valueSuffix: ' mm'
            }
        },
        {
            name: 'Current Rain',
            color: '#EB984E',
            type: 'column',
            yAxis: 0,
            data: data[2],
            tooltip: {
                valueSuffix: ' mm'
            }
        },
        {
            name: 'Avaliable Water',
            color: Highcharts.getOptions().colors[0],
            type: 'spline',
            yAxis: 3,
            data: data[3],
            tooltip: {
                valueSuffix: ' in'
            }
        },
        {
            name: 'Yield Potential',
            color: '#B03A2E',
            type: 'spline',
            yAxis: 4,
            data: data[4],
            tooltip: {
                valueSuffix: ''
            }
        }
    ]
});
function setLabelsSize(ch, size) {
    if (size == 'md') {
        var title = '30px';
        var label = '15px';
        var ticks = '15px';
    } else if (size == 'sm') {
        var title = '45px';
        var label = '25px';
        var ticks = '25px';
    } else {
        var title = '50px';
        var label = '30px';
        var ticks = '30px';
    }

    $('#legend-list').css('font-size', label);

    var x = [0];
    var y = [3, 4];

    ch.legend.update({
        itemStyle: {
            "fontSize": label
        }
    });

    ch.tooltip.update({
        style: {
            "fontSize": label
        }
    });

    x.forEach(function (e, i) {
        ch.xAxis[e].update({
            title: {
                style: {
                    "fontSize": label,
                }
            },
            labels: {
                style: {
                    "fontSize": ticks,
                }
            }
        });
    })


    y.forEach(function (e, i) {
        ch.yAxis[e].update({
            title: {
                style: {
                    "fontSize": label,
                }
            },
            labels: {
                style: {
                    "fontSize": ticks,
                }
            }
        });
    })




}

var curT = 'md';
function setChartSize(ch) {

    $('#resize').show();
    $('#charts').hide();

    var wHeight = $(window).height() - 20;

    if ($(window).width() >= 992) {
        ch.setSize(mdWidth, wHeight);
        //setLabelsSize(ch, 'md');
    } else if ($(window).width() >= 768) {
        curT = 'sm';
        ch.setSize(mdWidth * 2.13, wHeight);
        //setLabelsSize(ch, 'sm');
    } else {
        curT = 'xs';
        ch.setSize(mdWidth * 3.6, wHeight);
        // setLabelsSize(ch, 'xs');
    }

    $('#resize').hide();
    $('#charts').show();
}

var wHeight = $(window).height() - 20;
//setChartSize(chart);
chart.setSize(mdWidth, wHeight);
setLabelsSize(chart, 'md');

/*
 $(window).resize(function () {
 if ($(window).width() >= 992 && curT != 'md')
 setChartSize(chart);
 else if ($(window).width() >= 768 && curT != 'sm')
 setChartSize(chart);
 else if (curT != 'xs')
 setChartSize(chart);
 });*/