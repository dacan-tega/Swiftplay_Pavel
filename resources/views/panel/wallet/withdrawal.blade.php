@extends('layouts.web')

@push('styles')

@endpush

@section('content')
    <div class="container-fluid">
        @include('includes.navbar_top')
        @include('includes.navbar_left')

        <div class="page__content">
            <br>

            @include('includes.wallet_card')

            <div class="wallet-transactions mt-5">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">{{trans('panel.WITHDRAWAL_HISTORY')}}</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">{{trans('panel.Proof')}}</th>
                                    <th scope="col">{{trans('panel.Type')}}</th>
                                    <th scope="col">{{trans('panel.Value')}}</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">{{trans('panel.Data')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                    @if(count($withdrawals))
                                        @foreach($withdrawals as $withdrawal)
                                            <tr>
                                                <th scope="row">{{ $withdrawal->id }}</th>
                                                <td>
                                                    @if(!empty($withdrawal->proof))
                                                        <a href="{{ url('storage/'. $withdrawal->proof) }}" download class="text-success">{{trans('panel.To_go_down')}}</a>
                                                    @else
                                                        ....
                                                    @endif
                                                </td>
                                                <td>{{ $withdrawal->type }}</td>
                                                <td>{{ \Helper::amountFormatDecimal($withdrawal->amount) }}</td>
                                                <td>
                                                    @if($withdrawal->status == 0)
                                                        <span class="badge bg-warning text-dark">{{trans('panel.Pending')}}</span>
                                                    @else
                                                        <span class="badge bg-success">{{trans('panel.Confirmed')}}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $withdrawal->dateHumanReadable }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td class="flex items-center justify-center text-center py-4" colspan="5">
                                                <h4 class=" mb-0">{{trans('panel.NO_INFORMATION')}}</h4>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-5">
                            <div class="row">
                                <div class="col-lg-6"></div>
                                <div class="col-lg-6">
                                    {{ $withdrawals->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')

@endpush
