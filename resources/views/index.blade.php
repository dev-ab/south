@extends('app')

@section('title', $title)


@section('content')

@if(isset($message))
<div class='container'>
    <div class="row">
        <div class='col-sm-4 col-sm-offset-4'>
            @if($message == 'tokensuccess')
            <h4 class="alert alert-success alert-dismissable">The API token has been acquired successfully. It will be valid for a year starting today.</h4>
            @elseif($message == 'tokenerror')
            <h4 class="alert alert-danger alert-dismissable">Token request has been unsuccessful. Please try again and if the problem continues contact the developer.</h4>
            @endif
        </div>
    </div>
</div>
@endif


@if($status == 'no_token')
<div class='container'>
    <div class="row" style='margin-top:5%;'>
        <div class='col-sm-4 col-sm-offset-4'>
            <div class='panel panel-primary'>
                <div class='panel-heading'>Request Token</div>
                <div class='panel-body'>
                    <h5 class='alert alert-warning'>There's no valid access token at the moment. Click the button below to request a token.</h5>
                    <a class='btn btn-success pull-right' href='{{url('/request-token')}}'>Request Token</a>
                </div>
            </div>
        </div>
    </div>
</div>
@elseif($status == 'organizations')
<div class='container'>
    <div class="row" style='margin-top:5%;'>
        <div class='col-sm-4 col-sm-offset-4'>
            <div class='panel panel-primary'>
                <div class='panel-heading'>Select Organization</div>
                <div class='panel-body'>
                    <form method='post' action='/'>
                        <select name='org' class='form-control'>
                            <option value=''>Select Organization...</option>
                            @foreach($orgs['values'] as $org)
                            <option value='{{$org['id']}}'>{{$org['name']}}</option>
                            @endforeach
                        </select>
                        <input style='margin-top: 10px;' type='submit' value='Proceed' class='btn btn-success pull-right'>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @elseif($status == 'zones')
    <div class='container'>
        <div class="row" style='margin-top:5%;'>
            <div class='col-sm-4 col-sm-offset-4'>
                <div class='panel panel-primary'>
                    <div class='panel-heading'>Select Management Zone</div>
                    <div class='panel-body'>
                        <form method='post' action='/view-chart'>
                            <input name='org' type='hidden' value='{{$org}}'>
                            <select name='zone' class='form-control'>
                                <option value=''>Select Zone...</option>
                                @foreach($zones['values'] as $zone)
                                <option value='{{$zone['id']}}'>{{$zone['name']}}</option>
                                @endforeach
                            </select>
                            <input style='margin-top: 10px;' name="seedDate" type="text" class="form-control datepicker" placeholder="Choose Seeding Date">
                            <input style='margin-top: 10px;' name="endDate" type="text" class="form-control datepicker" placeholder="Choose Season End Date">
                            <input style='margin-top: 10px;' type='submit' value='View Chart' class='btn btn-success pull-right'>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @elseif($status == 'chart')
    <div class="container-fluid">
        <div class="row">
            <div class="col-xs-10 col-xs-offset-1" style="text-align: center;position: fixed;z-index: 9999;margin-bottom: 50px;">
                <h4>(Zone: <strong>"{{$zoneInfo['name']}}"</strong>)</h4>
                <ul id="legend-list" class="list-inline">
                    <li><i class="fa fa-circle" style="color:#F9E79F;"></i> 10 year rain avg</li>
                    <li><i class="fa fa-circle" style="color:#F4D03F;"></i> 5 year rain avg</li>
                    <li><i class="fa fa-circle" style="color:#EB984E;"></i> Current Rain</li>
                    <li><i class="fa fa-minus" style="color:#7cb5ec;"></i> Avaliable Water</li>
                    <li><i class="fa fa-minus" style="color:#B03A2E;"></i> Yield Potential</li>
                </ul>
            </div>
        </div>
        <div class="row">
            <div id="resize" class="col-xs-12" style="display: none;">
                <h2>Resizing...</h2>
            </div>

            <div id="charts" class="col-xs-12" style="margin-top: 50px;">
            </div>
        </div>
    </div>
    @endif
    @endsection

    @section('js')
    <script
        src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
    crossorigin="anonymous"></script>
    <script src="js/jquery-ui.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>

    <script>
$(function () {
    $(".datepicker").datepicker({dateFormat: 'yy-mm-dd'});
});
    </script>
    @if($status == 'chart')
    <script>
        var data = JSON.parse('<?= json_encode($aData); ?>');
        var mdWidth = JSON.parse('<?= json_encode($mdWidth); ?>');
        var mdHeight = 300;
    </script>
    <script src="js/main.js"></script>
    @endif
    @endsection