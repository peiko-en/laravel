<?php


namespace App\Http\Controllers;


use App\Models\Entity\Transaction;
use App\Models\Repository\TransactionsRepository;
use App\Services\Payments\Operations\Operation;
use App\Services\Payments\Payment;

class PaymentsController extends BaseController
{
    /**
     * @var TransactionsRepository
     */
    private $transactions;

    public function __construct(TransactionsRepository $transactions)
    {
        parent::__construct();

        $this->transactions = $transactions;
    }

    /**
     * @param string $slug payment slug
     * @param string $case
     * @return mixed
     * @throws \ReflectionException
     */
    public function index($slug, $case)
    {
        $payment = Payment::getInstance($slug);
        if (!$payment) {
            $this->notFound();
        }

        $payment->debug($case . ':');
        $payment->debug($this->request->all());
        $payment->debug('extractTransactionToken: ' . $payment->extractTransactionToken($this->request));

        $transaction = $this->transactions->findByToken($payment->extractTransactionToken($this->request));
        if (!$transaction) {
            $this->notFound();
        }

        $payment->setTransaction($transaction);

        $operation = Operation::getInstance($transaction->operation, [
            'transaction' => $transaction
        ]);

        if (!$operation) {
            $this->notFound();
        }

        if ($case == $payment::CASE_PROCESS) {
            if ($payment->verify($case)) {
                $operation->process();
                $transaction->response = json_encode($this->request->all());
                $transaction->changeStatus(Transaction::STATUS_SUCCESS);

                if ($payment->getSlug() == 'test' || $payment->getSlug() == 'balance') {
                    return redirect()->route('payment.case', [
                        'slug' => $slug,
                        'case' => 'success',
                        $payment->getTransactionTokenParam() => $transaction->token,
                    ]);
                }

                exit;
            } else {
                $payment->debug($slug . ' - not verified');
            }
        } elseif ($case == $payment::CASE_SUCCESS)  {
            $operation->success();
        } elseif ($case == $payment::CASE_FAIL)  {
            $operation->fail();
            $transaction->changeStatus(Transaction::STATUS_FAIL);
        } else {
            $this->notFound();
        }

        return $this->view(compact('payment', 'transaction', 'operation'));
    }
}